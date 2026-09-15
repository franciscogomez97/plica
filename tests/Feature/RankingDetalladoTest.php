<?php

namespace Tests\Feature;

use App\Filament\Pages\Ranking;
use App\Filament\Pages\RankingSeccion;
use App\Models\Club;
use App\Models\Manga;
use App\Models\Seccion;
use App\Models\Temporada;
use App\Models\User;
use App\Services\Scoring;
use Database\Seeders\DemoSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** El cuadro manga a manga de una sección: quién ganó cada manga y quién va ganando. */
class RankingDetalladoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        $this->actingAs(User::where('email', 'admin@plica.test')->firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_el_cuadro_tiene_una_columna_por_manga_y_el_mismo_orden_que_el_ranking(): void
    {
        $temporada = Temporada::firstOrFail();
        $orilla = Seccion::where('nombre', 'Orilla')->firstOrFail();
        [$manga1, $manga2] = Manga::where('seccion_id', $orilla->id)->where('estado', Manga::ESTADO_CELEBRADA)->orderBy('fecha')->get();

        $cuadro = Scoring::cuadroSeccion($temporada, $orilla);

        $this->assertSame([$manga1->id, $manga2->id], $cuadro->mangas->pluck('id')->all());
        $this->assertSame(
            ['Mario López', 'Sergio del Río', 'Alberto Rey', 'Toni Salgado'],
            $cuadro->filas->map(fn ($f) => $f->socio->nombre)->all(),
        );

        // Mario: 1º en la 1ª (4,350 kg), 3º en la 2ª (2,100 kg); total 6.450 g.
        $mario = $cuadro->filas[0];
        $this->assertSame(1, $mario->puesto);
        $this->assertSame(6450, $mario->puntos);
        $this->assertSame('4,350 kg', $mario->celdas[$manga1->id]->texto);
        $this->assertSame(1, $mario->celdas[$manga1->id]->puesto);
        $this->assertSame(3, $mario->celdas[$manga2->id]->puesto);
        $this->assertFalse($mario->celdas[$manga2->id]->descartada);

        // Sergio ganó la 2ª manga.
        $sergio = $cuadro->filas[1];
        $this->assertSame(1, $sergio->celdas[$manga2->id]->puesto);

        // Alberto no pescó nada en la 1ª: participó (0) pero no está ausente.
        $alberto = $cuadro->filas[2];
        $this->assertNotNull($alberto->celdas[$manga1->id]);
        $this->assertSame(0, $alberto->celdas[$manga1->id]->valor);
    }

    public function test_con_descartes_la_peor_manga_de_cada_uno_queda_marcada_y_no_suma(): void
    {
        $temporada = Temporada::firstOrFail();
        $orilla = Seccion::where('nombre', 'Orilla')->firstOrFail();
        $orilla->update(['descartes' => 1]);
        [$manga1, $manga2] = Manga::where('seccion_id', $orilla->id)->where('estado', Manga::ESTADO_CELEBRADA)->orderBy('fecha')->get();

        $cuadro = Scoring::cuadroSeccion($temporada, $orilla->fresh());

        $mario = $cuadro->filas->first(fn ($f) => $f->socio->nombre === 'Mario López');
        $this->assertSame(4350, $mario->puntos); // solo cuenta su mejor manga
        $this->assertFalse($mario->celdas[$manga1->id]->descartada);
        $this->assertTrue($mario->celdas[$manga2->id]->descartada);

        // Y el orden sigue siendo el del ranking de temporada con esos descartes.
        $ranking = Scoring::rankingTemporada($temporada)->firstWhere('nombre', 'Orilla');
        $this->assertSame(
            $ranking->filas->map(fn ($f) => $f->socio->id)->all(),
            $cuadro->filas->map(fn ($f) => $f->socio->id)->all(),
        );
    }

    public function test_quien_falta_a_una_manga_tiene_la_celda_vacia(): void
    {
        $temporada = Temporada::firstOrFail();
        $orilla = Seccion::where('nombre', 'Orilla')->firstOrFail();
        $manga2 = Manga::where('seccion_id', $orilla->id)->where('estado', Manga::ESTADO_CELEBRADA)->orderBy('fecha')->get()[1];

        // Toni no fue a la 2ª manga.
        $manga2->participacions()->whereHas('socio', fn ($q) => $q->where('nombre', 'Toni Salgado'))->delete();

        $cuadro = Scoring::cuadroSeccion($temporada, $orilla);
        $toni = $cuadro->filas->first(fn ($f) => $f->socio->nombre === 'Toni Salgado');

        $this->assertNull($toni->celdas[$manga2->id]);
        $this->assertSame(1, $toni->mangas);
    }

    public function test_la_pagina_se_ve_y_el_ranking_enlaza_a_ella(): void
    {
        $orilla = Seccion::where('nombre', 'Orilla')->firstOrFail();
        $url = RankingSeccion::getUrl(['seccion' => $orilla->id]);

        // El ranking del admin: una pestaña por sección (como Mangas) y, dentro, el cuadro manga a manga directo.
        $this->get(Ranking::getUrl(['seccion' => 'orilla']))
            ->assertOk()
            ->assertSee('Orilla')
            ->assertSee('Embarcación')
            ->assertSee('Pato — Lucio')
            ->assertSee('Líder')
            ->assertSee('Pieza mayor de la temporada')
            ->assertSee('Clasificación general')
            ->assertSee('ganador de la manga')
            ->assertSee('Mario L.')
            ->assertSee('Última manga')
            ->assertDontSee('Ver manga a manga');

        // La pestaña se recuerda: al volver sin parámetro sigue en Orilla.
        $this->get(Ranking::getUrl())->assertOk()->assertSee('Clasificación general')->assertSee('4,350');
        // Y cambiar de pestaña cambia de sección.
        $this->get(Ranking::getUrl(['seccion' => 'pato-lucio']))->assertOk()->assertSee('Ranking Pato — Lucio');

        $this->get($url)
            ->assertOk()
            ->assertSee('Ranking detallado — Orilla')
            ->assertSee('Atrás')
            ->assertSee('1ª Manga')
            ->assertSee('2ª Manga')
            ->assertSee('Mario López')
            ->assertSee('Mario L.') // nombre abreviado para la columna fija del móvil
            ->assertSee('4,350')
            ->assertSee('6,450')
            ->assertSee('ganador de la manga');
    }

    public function test_la_seccion_de_otro_club_no_existe_para_este_admin(): void
    {
        $otro = Club::create(['nombre' => 'Otro', 'slug' => 'otro']);
        $ajena = Seccion::create(['club_id' => $otro->id, 'nombre' => 'Ajena']);

        $this->get(RankingSeccion::getUrl(['seccion' => $ajena->id]))->assertNotFound();
    }
}
