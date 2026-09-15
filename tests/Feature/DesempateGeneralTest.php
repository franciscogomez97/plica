<?php

namespace Tests\Feature;

use App\Filament\Resources\Seccions\Pages\EditSeccion;
use App\Models\Captura;
use App\Models\Club;
use App\Models\Manga;
use App\Models\Participacion;
use App\Models\Seccion;
use App\Models\Socio;
use App\Models\User;
use App\Services\Scoring;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Sistema de la federación: el empate en la general es una regla aparte del
 * de la manga (14 de septiembre de 2026). Dos socios que suman lo mismo en el
 * año se ordenan según lo que diga la sección: comparten, más peso, mejor
 * manga, pieza mayor, menos piezas o más piezas.
 */
class DesempateGeneralTest extends TestCase
{
    use RefreshDatabase;

    private Seccion $seccion;

    private Socio $ana;

    private Socio $beto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('plica:club', ['nombre' => 'Club Empates', 'admin_email' => 'admin@empates.test'])->assertSuccessful();
        $club = Club::where('slug', 'club-empates')->firstOrFail();
        $this->seccion = $club->seccions()->create([
            'nombre' => 'Orilla', 'criterio' => Seccion::CRITERIO_PESO, 'sistema_puntuacion' => Seccion::SISTEMA_PUESTOS,
            'desempate' => Seccion::DESEMPATE_PROMEDIO, 'puntos_no_asistencia' => 0,
        ]);
        $this->ana = $club->socios()->create(['nombre' => 'Ana']);
        $this->beto = $club->socios()->create(['nombre' => 'Beto']);
        $temporada = $club->temporadaActiva();

        // Dos mangas: Ana gana la 1ª y es 2ª en la 2ª; Beto al revés. Los dos suman 3.
        // Ana: 3.000 g en dos piezas (mayor 2.000) + 500 g en una = 3.500 g, 3 piezas, mayor 2.000.
        // Beto: 1.000 g en una + 4.000 g en cuatro (mayor 1.500) = 5.000 g, 5 piezas, mayor 1.500.
        foreach ([
            ['fecha' => '2026-03-01', 'ana' => [2, 3000, 2000], 'beto' => [1, 1000, 1000]],
            ['fecha' => '2026-04-01', 'ana' => [1, 500, 500], 'beto' => [4, 4000, 1500]],
        ] as $def) {
            $manga = Manga::create(['temporada_id' => $temporada->id, 'seccion_id' => $this->seccion->id, 'nombre' => 'Manga', 'fecha' => $def['fecha'], 'estado' => Manga::ESTADO_CELEBRADA]);
            foreach (['ana' => $this->ana, 'beto' => $this->beto] as $clave => $socio) {
                [$piezas, $gramos, $mayor] = $def[$clave];
                $p = Participacion::create(['manga_id' => $manga->id, 'socio_id' => $socio->id, 'seccion_id' => $this->seccion->id, 'plica' => true, 'pieza_mayor_gramos' => $mayor]);
                Captura::create(['participacion_id' => $p->id, 'piezas' => $piezas, 'peso_gramos' => $gramos]);
            }
        }
    }

    /** @return array<string, int> nombre => puesto */
    private function puestos(): array
    {
        $grupo = Scoring::rankingTemporada($this->seccion->club->temporadaActiva())->firstWhere('seccionId', $this->seccion->id);

        return $grupo->filas->mapWithKeys(fn (object $f) => [$f->socio->nombre => $f->puesto])->all();
    }

    public function test_cada_regla_de_la_general_ordena_como_dice(): void
    {
        // Los dos suman 3 puntos (1 + 2 y 2 + 1): todo depende de la regla de la general.
        foreach (['Ana', 'Beto'] as $nombre) {
            $this->assertSame(3, Scoring::rankingTemporada($this->seccion->club->temporadaActiva())->first()->filas->first(fn ($f) => $f->socio->nombre === $nombre)->puntos);
        }

        $casos = [
            Seccion::DESEMPATE_COMPARTIDO => ['Ana' => 1, 'Beto' => 1],          // nadie: comparten el 1º
            Seccion::DESEMPATE_PESO => ['Beto' => 1, 'Ana' => 2],                // Beto 5.000 g > Ana 3.500 g
            Seccion::DESEMPATE_GENERAL_MEJOR_MANGA => ['Ana' => 1, 'Beto' => 1], // los dos tienen un 1º: siguen igual
            Seccion::DESEMPATE_PIEZA_MAYOR => ['Ana' => 1, 'Beto' => 2],         // Ana 2.000 g > Beto 1.500 g
            Seccion::DESEMPATE_MENOS_PIEZAS => ['Ana' => 1, 'Beto' => 2],        // Ana 3 piezas < Beto 5
            Seccion::DESEMPATE_PIEZAS => ['Beto' => 1, 'Ana' => 2],              // Beto 5 piezas > Ana 3
        ];

        foreach ($casos as $regla => $esperado) {
            $this->seccion->update(['desempate_general' => $regla]);
            $this->assertSame($esperado, array_intersect_key($this->puestos(), $esperado), $regla);
        }

        // «Mejor manga» de verdad: Beto pasa a 1º y 3º (suma 4) y Ana a 2º y 2º (suma 4) con un tercero: gana Beto (tiene un 1º).
        $carlos = $this->seccion->club->socios()->create(['nombre' => 'Carlos']);
        $segunda = $this->seccion->mangas()->orderBy('fecha')->get()[1];
        $p = Participacion::create(['manga_id' => $segunda->id, 'socio_id' => $carlos->id, 'seccion_id' => $this->seccion->id, 'plica' => true, 'pieza_mayor_gramos' => 900]);
        Captura::create(['participacion_id' => $p->id, 'piezas' => 1, 'peso_gramos' => 900]);
        // 2ª manga ahora: Beto 4.000 (1º), Carlos 900 (2º), Ana 500 (3º). Ana: 1 + 3 = 4; Beto: 2 + 1 = 3. Ya no empatan…
        $this->seccion->update(['desempate_general' => Seccion::DESEMPATE_GENERAL_MEJOR_MANGA]);
        $this->assertSame(['Beto' => 1, 'Ana' => 2], array_intersect_key($this->puestos(), ['Beto' => 0, 'Ana' => 0]));
    }

    public function test_la_mejor_manga_desempata_y_un_ausente_sin_mangas_no_la_tiene(): void
    {
        // Ana: 1º y 3º (suma 4). Beto: 2º y 2º (suma 4). Con «mejor manga», gana Ana (tiene un 1º).
        $segunda = $this->seccion->mangas()->orderBy('fecha')->get()[1];
        $carlos = $this->seccion->club->socios()->create(['nombre' => 'Carlos']);
        $p = Participacion::create(['manga_id' => $segunda->id, 'socio_id' => $carlos->id, 'seccion_id' => $this->seccion->id, 'plica' => true, 'pieza_mayor_gramos' => 6000]);
        Captura::create(['participacion_id' => $p->id, 'piezas' => 1, 'peso_gramos' => 6000]); // Carlos 1º de la 2ª: Beto 2º, Ana 3º
        $this->seccion->update(['desempate_general' => Seccion::DESEMPATE_GENERAL_MEJOR_MANGA, 'puntos_no_asistencia' => 5]);

        $puestos = $this->puestos();
        $this->assertSame(1, $puestos['Ana']);
        $this->assertSame(2, $puestos['Beto']);
        // Carlos: 5 (ausente en la 1ª) + 1 = 6, tercero. Y alguien de la sección sin ninguna manga va el último, sin mejor manga.
        $dani = $this->seccion->club->socios()->create(['nombre' => 'Dani']);
        $this->seccion->socios()->attach($dani->id);
        $puestos = $this->puestos();
        $this->assertSame(3, $puestos['Carlos']);
        $this->assertSame(4, $puestos['Dani']);
    }

    public function test_la_regla_de_la_general_se_configura_solo_sumando_puestos_y_se_cuenta_en_las_reglas(): void
    {
        $this->actingAs(User::where('email', 'admin@empates.test')->firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(EditSeccion::class, ['record' => $this->seccion->getRouteKey()])
            ->assertFormFieldExists('desempate_general')
            ->assertSee('Empate en una manga')
            ->assertSee('Empate en la clasificación general')
            ->fillForm(['desempate_general' => Seccion::DESEMPATE_PESO])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame(Seccion::DESEMPATE_PESO, $this->seccion->fresh()->desempate_general);
        $this->assertStringContainsString(
            'Si empatan en una manga, se reparten el promedio de sus puestos; si empatan en el ranking, gana quien más peso haya sacado en el año; si siguen igual, comparten puesto.',
            $this->seccion->fresh()->resumenReglas(),
        );

        // Sumando lo pescado no existe (la misma regla vale para manga y año) y no se ve.
        Livewire::test(EditSeccion::class, ['record' => $this->seccion->getRouteKey()])
            ->fillForm(['sistema_puntuacion' => Seccion::SISTEMA_ACUMULADO])
            ->assertFormFieldIsHidden('desempate_general')
            ->assertSee('Empate');

        // Una regla que no encaja con el criterio (más cm en una sección de peso) vuelve a «comparten».
        $this->seccion->refresh(); // el formulario guardó por otra instancia
        $this->seccion->update(['desempate_general' => Seccion::DESEMPATE_GENERAL_MEDIDA]);
        $this->assertSame(Seccion::DESEMPATE_COMPARTIDO, $this->seccion->fresh()->desempate_general);
        $this->seccion->update(['criterio' => Seccion::CRITERIO_MEDIDA, 'desempate_general' => Seccion::DESEMPATE_GENERAL_MEDIDA]);
        $this->assertSame(Seccion::DESEMPATE_GENERAL_MEDIDA, $this->seccion->fresh()->desempate_general);
    }
}
