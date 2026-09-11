<?php

namespace Tests\Feature;

use App\Filament\Resources\Mangas\Pages\PesajeManga;
use App\Models\Captura;
use App\Models\Club;
use App\Models\Manga;
use App\Models\Participacion;
use App\Models\Seccion;
use App\Models\Socio;
use App\Models\Temporada;
use App\Models\User;
use App\Services\Compartir;
use App\Services\Scoring;
use Database\Seeders\DemoSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pieza mayor (siempre hay premio) y desempates configurables por sección.
 * Si ni el desempate lo resuelve, se comparte el puesto: nunca decide el azar.
 */
class PiezaMayorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    private function apuntar(Manga $manga, Socio $socio, int $piezas, int $gramos, ?int $mayor = null): Participacion
    {
        $p = Participacion::create([
            'manga_id' => $manga->id,
            'socio_id' => $socio->id,
            'seccion_id' => $manga->seccion_id,
            'pieza_mayor_gramos' => $mayor,
        ]);
        Captura::create(['participacion_id' => $p->id, 'piezas' => $piezas, 'peso_gramos' => $gramos]);

        return $p;
    }

    public function test_la_pieza_mayor_se_apunta_en_el_pesaje_rapido_y_con_un_solo_pez_se_deduce(): void
    {
        $this->actingAs(User::where('email', 'admin@plica.test')->firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $manga = Manga::where('estado', Manga::ESTADO_CELEBRADA)->orderBy('fecha')->firstOrFail();
        $mario = $manga->participacions()->whereHas('socio', fn ($q) => $q->where('nombre', 'Mario López'))->firstOrFail();
        $toni = $manga->participacions()->whereHas('socio', fn ($q) => $q->where('nombre', 'Toni Salgado'))->firstOrFail();

        Livewire::test(PesajeManga::class, ['record' => $manga->getRouteKey()])
            // Toni sacó un solo pez: su pieza mayor es ese pez, sin teclear nada.
            ->assertSet("filas.{$toni->id}.mayor", '720')
            ->set("filas.{$mario->id}.mayor", '2.500')
            ->assertSet("estados.{$mario->id}.texto", '3 piezas · 4,350 kg · mayor 2,500 kg')
            // Un pez no puede pesar más que el total.
            ->set("filas.{$mario->id}.mayor", '5000')
            ->assertSet("estados.{$mario->id}.tipo", 'error');

        $this->assertSame(2500, $mario->fresh()->pieza_mayor_gramos);
    }

    public function test_clasificacion_ranking_y_whatsapp_dicen_quien_tiene_la_pieza_mayor(): void
    {
        $temporada = Temporada::firstOrFail();
        $primera = fn (string $seccion) => Manga::whereHas('seccion', fn ($q) => $q->where('nombre', $seccion))
            ->where('estado', Manga::ESTADO_CELEBRADA)->orderBy('fecha')->firstOrFail();

        $orilla1 = Scoring::clasificacionManga($primera('Orilla'))->first();
        $this->assertSame('Mario López', $orilla1->piezaMayor->socio->nombre);
        $this->assertSame('2,100 kg', $orilla1->piezaMayor->texto);
        $this->assertSame('Jorge Vidal', Scoring::clasificacionManga($primera('Embarcación'))->first()->piezaMayor->socio->nombre);
        // Por medida, la pieza mayor es el pez más largo.
        $this->assertSame('120,5 cm', Scoring::clasificacionManga($primera('Pato — Lucio'))->first()->piezaMayor->texto);

        $ranking = Scoring::rankingTemporada($temporada);
        $embarcacion = $ranking->firstWhere('nombre', 'Embarcación');
        $this->assertSame('Jorge Vidal', $embarcacion->piezaMayor->socio->nombre);
        $this->assertSame('2,400 kg', $embarcacion->piezaMayor->texto);
        $this->assertSame('2ª Manga', $embarcacion->piezaMayor->manga->nombre);

        $club = Club::where('slug', 'cd-pesca-piloto')->firstOrFail();
        $this->assertStringContainsString('🐟 Pieza mayor de la temporada: Jorge Vidal · 2,400 kg (2ª Manga)', Compartir::textoRanking($club, $temporada, $embarcacion));
        $this->assertStringContainsString('🐟 Pieza mayor: Mario López · 2,100 kg', Compartir::textoManga($primera('Orilla'), Scoring::clasificacionManga($primera('Orilla'))));

        $this->actingAs(User::where('email', 'admin@plica.test')->firstOrFail());
        $this->get('/admin/mangas/'.$primera('Orilla')->id.'/clasificacion')->assertOk()->assertSee('Pieza mayor:')->assertSee('mayor 2,100 kg');
        $this->get('/admin/ranking')->assertOk()->assertSee('Pieza mayor de la temporada');
        $this->get('/c/cd-pesca-piloto/embarcacion')->assertOk()->assertSee('Pieza mayor de la temporada')->assertSee('Jorge Vidal');
    }

    public function test_el_desempate_lo_decide_la_seccion_y_si_siguen_iguales_comparten_puesto(): void
    {
        $club = Club::where('slug', 'cd-pesca-piloto')->firstOrFail();
        $seccion = Seccion::create(['club_id' => $club->id, 'nombre' => 'Empates', 'criterio' => Seccion::CRITERIO_PESO]);
        $manga = Manga::create(['temporada_id' => Temporada::firstOrFail()->id, 'seccion_id' => $seccion->id, 'nombre' => 'Manga de empates', 'fecha' => today(), 'estado' => Manga::ESTADO_CELEBRADA]);
        [$a, $b, $c] = Socio::where('club_id', $club->id)->orderBy('id')->limit(3)->get();

        $this->apuntar($manga, $a, piezas: 2, gramos: 3000, mayor: 2000);
        $this->apuntar($manga, $b, piezas: 3, gramos: 3000, mayor: 1500);
        $this->apuntar($manga, $c, piezas: 1, gramos: 1000);

        $puestos = fn () => Scoring::clasificacionManga($manga)->first()->filas->mapWithKeys(fn ($f) => [$f->socio->id => $f->puesto])->all();

        // Por defecto (peso): desempata quien más piezas saque.
        $this->assertSame(Seccion::DESEMPATE_PIEZAS, $seccion->desempate);
        $this->assertSame([$b->id => 1, $a->id => 2, $c->id => 3], $puestos());

        // La sección decide: pieza mayor.
        $seccion->update(['desempate' => Seccion::DESEMPATE_PIEZA_MAYOR]);
        $this->assertSame([$a->id => 1, $b->id => 2, $c->id => 3], $puestos());

        // Iguales en todo: comparten el 1º y el siguiente es 3º.
        Participacion::where('socio_id', $a->id)->update(['pieza_mayor_gramos' => 1500]);
        Captura::whereIn('participacion_id', Participacion::where('socio_id', $a->id)->pluck('id'))->update(['piezas' => 3]);
        $this->assertSame([$a->id => 1, $b->id => 1, $c->id => 3], $puestos());
    }

    public function test_el_ranking_de_temporada_desempata_con_la_misma_regla(): void
    {
        $club = Club::where('slug', 'cd-pesca-piloto')->firstOrFail();
        $seccion = Seccion::create(['club_id' => $club->id, 'nombre' => 'Empates', 'criterio' => Seccion::CRITERIO_PESO]);
        $temporada = Temporada::firstOrFail();
        $base = ['temporada_id' => $temporada->id, 'seccion_id' => $seccion->id, 'estado' => Manga::ESTADO_CELEBRADA];
        $m1 = Manga::create([...$base, 'nombre' => 'E1', 'fecha' => today()->subDays(7)]);
        $m2 = Manga::create([...$base, 'nombre' => 'E2', 'fecha' => today()]);
        [$a, $b] = Socio::where('club_id', $club->id)->orderBy('id')->limit(2)->get();

        $this->apuntar($m1, $a, piezas: 1, gramos: 3000, mayor: 3000);
        $this->apuntar($m2, $a, piezas: 1, gramos: 2000, mayor: 2000);
        $this->apuntar($m1, $b, piezas: 2, gramos: 2500, mayor: 1400);
        $this->apuntar($m2, $b, piezas: 2, gramos: 2500, mayor: 1400);

        $orden = fn () => Scoring::rankingTemporada($temporada)->firstWhere('nombre', 'Empates')->filas->map(fn ($f) => [$f->socio->id, $f->puesto])->all();

        // Ambos 5.000 g. Por piezas gana B (4 contra 2).
        $this->assertSame([[$b->id, 1], [$a->id, 2]], $orden());

        // Por pieza mayor gana A (3.000 g).
        $seccion->update(['desempate' => Seccion::DESEMPATE_PIEZA_MAYOR]);
        $this->assertSame([[$a->id, 1], [$b->id, 2]], $orden());

        // Y el cuadro manga a manga enseña la pieza mayor de la temporada y la de cada manga.
        $cuadro = Scoring::cuadroSeccion($temporada, $seccion->fresh());
        $this->assertSame($a->id, $cuadro->piezaMayor->socio->id);
        $this->assertSame('3,000 kg', $cuadro->piezaMayor->texto);
        $filaA = $cuadro->filas->first(fn ($f) => $f->socio->id === $a->id);
        $filaB = $cuadro->filas->first(fn ($f) => $f->socio->id === $b->id);
        $this->assertTrue($filaA->celdas[$m1->id]->mayorDeLaManga);   // 3.000 g contra 1.400 g
        $this->assertTrue($filaA->celdas[$m2->id]->mayorDeLaManga);   // 2.000 g contra 1.400 g
        $this->assertFalse($filaB->celdas[$m1->id]->mayorDeLaManga);
        $this->assertFalse($filaB->celdas[$m2->id]->mayorDeLaManga);
    }

    public function test_una_seccion_por_piezas_no_puede_desempatar_por_piezas(): void
    {
        $club = Club::where('slug', 'cd-pesca-piloto')->firstOrFail();
        $seccion = Seccion::create(['club_id' => $club->id, 'nombre' => 'Por piezas', 'criterio' => Seccion::CRITERIO_PIEZAS, 'desempate' => Seccion::DESEMPATE_PIEZAS]);

        $this->assertSame(Seccion::DESEMPATE_PESO, $seccion->fresh()->desempate);
        $this->assertSame([Seccion::DESEMPATE_PIEZA_MAYOR, Seccion::DESEMPATE_PESO, Seccion::DESEMPATE_COMPARTIDO], array_keys(Seccion::desempatesPara(Seccion::CRITERIO_PIEZAS)));
        $this->assertSame([Seccion::DESEMPATE_PIEZA_MAYOR, Seccion::DESEMPATE_PIEZAS, Seccion::DESEMPATE_COMPARTIDO], array_keys(Seccion::desempatesPara(Seccion::CRITERIO_PESO)));
        // Sumando puestos, además, «se reparten el promedio».
        $this->assertArrayHasKey(Seccion::DESEMPATE_PROMEDIO, Seccion::desempatesPara(Seccion::CRITERIO_PESO, Seccion::SISTEMA_PUESTOS));
        $this->assertArrayNotHasKey(Seccion::DESEMPATE_PROMEDIO, Seccion::desempatesPara(Seccion::CRITERIO_PESO));

        $this->actingAs(User::where('email', 'admin@plica.test')->firstOrFail());
        $this->get('/admin/seccions/create')->assertOk()->assertSee('Si empatan, gana')->assertSee('La pieza mayor');
    }
}
