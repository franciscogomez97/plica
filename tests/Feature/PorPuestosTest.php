<?php

namespace Tests\Feature;

use App\Filament\App\Pages\RankingSeccion;
use App\Filament\Resources\Seccions\Pages\EditSeccion;
use App\Models\Captura;
use App\Models\Club;
use App\Models\Manga;
use App\Models\Participacion;
use App\Models\Seccion;
use App\Models\Socio;
use App\Models\Temporada;
use App\Models\User;
use App\Services\Scoring;
use Database\Seeders\BassExtremaduraSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El sistema «por puestos» de federación, con la hoja de Bass Extremadura
 * (una manga de Orilla, septiembre de 2026) como prueba: puesto = puntos,
 * empatados con el promedio, ir y no pescar cuenta, no ir cuesta 48.
 */
class PorPuestosTest extends TestCase
{
    use RefreshDatabase;

    /** La hoja: nombre => [piezas, gramos, pieza mayor]. [0, 0, 0] = fue y no pescó. Quien no está, no fue. */
    private const HOJA = [
        'Fernando Díaz' => [5, 5225, 1525], 'Angel Vazquez' => [3, 3250, 1250], 'Victor Moreno' => [4, 2980, 830],
        'Fco. Javier Moreno' => [4, 2860, 985], 'Rafael Tomé' => [3, 2635, 1265], 'Francisco Dorado' => [2, 1355, 855],
        'Jorge Kopa' => [2, 1250, 845], 'Manuel Martín' => [3, 1130, 460], 'Ismael Arroyo' => [3, 1040, 405],
        'Diego Ruiz' => [1, 1015, 1015], 'Miguel García' => [1, 995, 995], 'David Machuca' => [2, 980, 650],
        'David Granado' => [2, 770, 430], 'Felix Daniel Rodriguez' => [1, 750, 750], 'Alberto Enrique' => [2, 740, 460],
        'Adrián Fernandez' => [1, 675, 675], 'Antonio Calvo' => [1, 480, 480], 'Oscar Hidalgo' => [1, 435, 435],
        'Pedro Yuste' => [1, 435, 435], 'Cristofer Pérez' => [1, 395, 395], 'Juan Pedro Jerez' => [1, 260, 260],
        'Eduardo Vega' => [0, 0, 0], 'Alvaro Tarifa' => [0, 0, 0], 'Eduardo Soria' => [0, 0, 0], 'Javier Soria' => [0, 0, 0],
        'Jose María Lopez' => [0, 0, 0], 'Josué Mateos' => [0, 0, 0], 'Oscar Ramos' => [0, 0, 0], 'Pablo Liberal' => [0, 0, 0],
    ];

    /** Los puntos de la columna «Puntos» de la hoja. */
    private const PUNTOS = [
        'Fernando Díaz' => 1, 'Angel Vazquez' => 2, 'Victor Moreno' => 3, 'Fco. Javier Moreno' => 4, 'Rafael Tomé' => 5,
        'Francisco Dorado' => 6, 'Jorge Kopa' => 7, 'Manuel Martín' => 8, 'Ismael Arroyo' => 9, 'Diego Ruiz' => 10,
        'Miguel García' => 11, 'David Machuca' => 12, 'David Granado' => 13, 'Felix Daniel Rodriguez' => 14,
        'Alberto Enrique' => 15, 'Adrián Fernandez' => 16, 'Antonio Calvo' => 17, 'Oscar Hidalgo' => 18.5,
        'Pedro Yuste' => 18.5, 'Cristofer Pérez' => 20, 'Juan Pedro Jerez' => 21,
        'Eduardo Vega' => 25.5, 'Alvaro Tarifa' => 25.5, 'Eduardo Soria' => 25.5, 'Javier Soria' => 25.5,
        'Jose María Lopez' => 25.5, 'Josué Mateos' => 25.5, 'Oscar Ramos' => 25.5, 'Pablo Liberal' => 25.5,
    ];

    private Club $club;

    private Seccion $orilla;

    private Temporada $temporada;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BassExtremaduraSeeder::class);
        $this->club = Club::where('slug', 'bass-extremadura')->firstOrFail();
        $this->orilla = Seccion::where('club_id', $this->club->id)->where('slug', 'orilla')->firstOrFail();
        $this->temporada = Temporada::where('club_id', $this->club->id)->firstOrFail();
    }

    private function manga(string $nombre, array $resultados, int $diasAtras): Manga
    {
        $manga = Manga::create(['temporada_id' => $this->temporada->id, 'seccion_id' => $this->orilla->id, 'nombre' => $nombre, 'fecha' => today()->subDays($diasAtras), 'lugar' => 'Embalse', 'estado' => Manga::ESTADO_CELEBRADA]);

        foreach ($resultados as $socio => [$piezas, $gramos, $mayor]) {
            $p = Participacion::create(['manga_id' => $manga->id, 'socio_id' => Socio::where('club_id', $this->club->id)->where('nombre', $socio)->firstOrFail()->id, 'seccion_id' => $this->orilla->id, 'plica' => true, 'pieza_mayor_gramos' => $mayor ?: null]);
            if ($piezas > 0) {
                Captura::create(['participacion_id' => $p->id, 'piezas' => $piezas, 'peso_gramos' => $gramos]);
            }
        }

        return $manga;
    }

    private function puntos(): array
    {
        return Scoring::rankingTemporada($this->temporada)->first()->filas
            ->mapWithKeys(fn (object $f) => [$f->socio->nombre => $f->puntos])->all();
    }

    public function test_la_seccion_y_los_socios_son_los_de_la_hoja(): void
    {
        $this->assertSame(47, $this->club->socios()->count());
        $this->assertSame(Seccion::SISTEMA_PUESTOS, $this->orilla->sistema_puntuacion);
        $this->assertSame(Seccion::DESEMPATE_PROMEDIO, $this->orilla->desempate);
        $this->assertSame(48, $this->orilla->puntos_no_asistencia);
        $this->assertSame(
            'Cada manga la gana quien más peso saca. El ranking suma los puestos de cada manga: gana quien menos suma. Ir y no pescar (bolo) vale la media de los puestos que quedan tras los que pescaron. No ir a una manga cuesta 48 puntos. Si empatan en una manga, se reparten el promedio de sus puestos; si empatan en el ranking, comparten puesto.',
            $this->orilla->resumenReglas(),
        );

        // Se puede volver a ejecutar sin duplicar.
        $this->seed(BassExtremaduraSeeder::class);
        $this->assertSame(47, $this->club->socios()->count());
        $this->assertSame(3, Seccion::where('club_id', $this->club->id)->count()); // Orilla, Pato y Embarcación
    }

    public function test_una_manga_puntua_como_la_hoja_del_club(): void
    {
        $this->manga('1ª Manga', self::HOJA, 7);

        $puntos = $this->puntos();
        $this->assertCount(47, $puntos); // los 47 de la sección, también los 18 que no fueron (48 cada uno)

        foreach (self::PUNTOS as $nombre => $esperado) {
            $this->assertEquals($esperado, $puntos[$nombre], $nombre);
        }
        $this->assertEquals(48, $puntos['Sergio Guerrero']);
        $this->assertSame(18, count(array_filter($puntos, fn ($p) => $p == 48)));

        // Los empatados se ven con sus decimales.
        $this->assertSame('18,5', Scoring::formatPuntos(18.5));
        $this->assertSame('25,5', Scoring::formatPuntos(25.5));
        $this->assertSame('1', Scoring::formatPuntos(1));
        $this->assertSame('1.234', Scoring::formatPuntos(1234));

        // Pieza mayor de la manga y de la temporada: la de 1525 g de Fernando.
        $ranking = Scoring::rankingTemporada($this->temporada)->first();
        $this->assertSame('Fernando Díaz', $ranking->piezaMayor->socio->nombre);
        $this->assertSame(1525, $ranking->piezaMayor->valor);
    }

    public function test_no_ir_cuesta_48_y_la_general_suma_puestos(): void
    {
        $this->manga('1ª Manga', self::HOJA, 14);
        // 2ª manga: Sergio Guerrero (ausente en la 1ª) gana; Fernando no va; Ángel segundo.
        $this->manga('2ª Manga', ['Sergio Guerrero' => [3, 4000, 2000], 'Angel Vazquez' => [1, 1000, 1000], 'Pedro Yuste' => [0, 0, 0]], 7);

        $puntos = $this->puntos();
        $this->assertEquals(48 + 1, $puntos['Sergio Guerrero']);   // no fue a la 1ª (48), ganó la 2ª (1)
        $this->assertEquals(1 + 48, $puntos['Fernando Díaz']);     // ganó la 1ª, no fue a la 2ª
        $this->assertEquals(2 + 2, $puntos['Angel Vazquez']);
        $this->assertEquals(18.5 + 3, $puntos['Pedro Yuste']);     // fue y no pescó: 3º de tres
        $this->assertEquals(25.5 + 48, $puntos['Eduardo Vega']);

        // Menos puntos, mejor: Ángel (4) delante de Pedro (21,5), Sergio (49) y Fernando (49).
        $orden = Scoring::rankingTemporada($this->temporada)->first()->filas->pluck('socio.nombre')->values();
        $this->assertSame('Angel Vazquez', $orden[0]);
        $this->assertLessThan($orden->search('Sergio Guerrero'), $orden->search('Pedro Yuste'));

        // Con «0» vuelve a ser el último de la manga + 1: en la 2ª manga, 4.
        $this->orilla->update(['puntos_no_asistencia' => 0]);
        $this->assertEquals(1 + 4, $this->puntos()['Fernando Díaz']);

        // Con empates compartidos y bolo «primer puesto libre», Óscar y Pedro se llevan 18 (no 18,5) y los de cero, 22.
        $this->orilla->update(['puntos_no_asistencia' => 48, 'desempate' => Seccion::DESEMPATE_COMPARTIDO, 'bolo' => Seccion::BOLO_PRIMER_LIBRE]);
        $puntos = $this->puntos();
        $this->assertEquals(18 + 3, $puntos['Pedro Yuste']);
        $this->assertEquals(22 + 48, $puntos['Eduardo Vega']);
    }

    public function test_el_cuadro_manga_a_manga_ensena_los_puntos_de_cada_manga_y_lo_que_cuesta_no_ir(): void
    {
        $manga = $this->manga('1ª Manga', self::HOJA, 7);

        $cuadro = Scoring::cuadroSeccion($this->temporada, $this->orilla);
        $this->assertSame(Seccion::SISTEMA_PUESTOS, $cuadro->sistema);
        $this->assertSame([$manga->id => 48], $cuadro->ausentePorManga);
        $oscar = $cuadro->filas->first(fn (object $f) => $f->socio->nombre === 'Oscar Hidalgo');
        $this->assertEquals(18.5, $oscar->celdas[$manga->id]->puntos);
        $this->assertSame(18, $oscar->celdas[$manga->id]->puesto);

        $this->get('/c/bass-extremadura/orilla')->assertOk()
            ->assertSee('18,5')
            ->assertSee('25,5')
            ->assertSee('No ir a una manga cuesta 48 puntos')
            // Federación: en cada celda mandan los puntos de la manga (Ángel, 2º con 3.250 g: «2 pts»), y el peso va debajo.
            ->assertSeeInOrder(['Angel Vazquez', 'class="v pts font-semibold">2<span class="u"> pts</span>', 'class="peso">3,250 kg'], false)
            // Quien fue y no pescó: sus puntos de bolo y «bolo» donde iría el peso.
            ->assertSeeInOrder(['Eduardo Vega', 'class="v pts font-semibold">25,5<span class="u"> pts</span>', 'class="peso">0 kg'], false);

        // Y el cuadro del panel del socio, igual.
        $this->actingAs(User::create(['name' => 'Socio de prueba', 'email' => 'socio@be.test', 'password' => Hash::make('secreta1234'), 'club_id' => $this->club->id, 'role' => User::ROLE_SOCIO]));
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Livewire::test(RankingSeccion::class, ['seccion' => $this->orilla->id])
            ->assertSeeHtml('class="v pts">2<span class="u"> pts</span>')
            ->assertSeeHtml('class="peso">3,250 kg')
            ->assertSeeHtml('class="peso">0 kg');
    }

    public function test_el_formulario_de_la_seccion_configura_el_sistema_por_puestos(): void
    {
        $this->artisan('plica:club', ['nombre' => 'Club Puestos', 'admin_email' => 'admin@puestos.test'])->assertSuccessful();
        $admin = User::where('email', 'admin@puestos.test')->firstOrFail();
        $seccion = $admin->club->seccions()->create(['nombre' => 'Orilla']);

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(EditSeccion::class, ['record' => $seccion->getRouteKey()])
            ->assertFormFieldExists('sistema_puntuacion')
            ->assertFormFieldExists('puntos_participacion')
            ->fillForm([
                'sistema_puntuacion' => Seccion::SISTEMA_PUESTOS,
                'desempate' => Seccion::DESEMPATE_PROMEDIO,
                'ausencia_fija' => 1,
                'puntos_no_asistencia' => 48,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $seccion->refresh();
        $this->assertSame(Seccion::SISTEMA_PUESTOS, $seccion->sistema_puntuacion);
        $this->assertSame(Seccion::DESEMPATE_PROMEDIO, $seccion->desempate);
        $this->assertSame(48, $seccion->puntos_no_asistencia);
        $this->assertStringContainsString('se reparten el promedio', $seccion->resumenReglas());
    }
}
