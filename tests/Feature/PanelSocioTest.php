<?php

namespace Tests\Feature;

use App\Filament\App\Pages\ClasificacionManga;
use App\Filament\App\Pages\Inicio;
use App\Filament\App\Pages\RankingSeccion;
use App\Models\Club;
use App\Models\Manga;
use App\Models\Seccion;
use App\Models\Socio;
use App\Models\Temporada;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El Inicio del socio es un resumen: podio por sección y su puesto; el ranking
 * completo, el cuadro manga a manga y la clasificación entera están a un toque.
 */
class PanelSocioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('app'));
    }

    public function test_el_inicio_resume_cada_seccion_con_el_podio_y_tu_puesto(): void
    {
        $this->actingAs(User::where('email', 'socio@plica.test')->firstOrFail()); // Mario, 1º de Orilla
        $orilla = Seccion::where('nombre', 'Orilla')->firstOrFail();

        $this->get(Inicio::getUrl())->assertOk()
            ->assertSee('Ranking Orilla')
            ->assertSee('Vas <strong>1º</strong> de 4', escape: false)
            ->assertSee('Mario López · tú')
            ->assertSee('Ver ranking completo')
            ->assertSee(RankingSeccion::getUrl(['seccion' => $orilla->id]))
            ->assertSee('Ver clasificación completa')
            ->assertSee('Pieza mayor de la temporada')
            // Solo el podio: Toni (4º de Orilla) no aparece en el resumen.
            ->assertDontSee('Toni Salgado');
    }

    public function test_si_estas_fuera_del_podio_tu_fila_aparece_igualmente(): void
    {
        $toni = Socio::where('nombre', 'Toni Salgado')->firstOrFail();
        $user = User::create(['name' => 'Toni Salgado', 'email' => 'toni@plica.test', 'password' => 'secreto123', 'club_id' => $toni->club_id, 'role' => User::ROLE_SOCIO]);
        $toni->update(['user_id' => $user->id]);

        $this->actingAs($user)->get(Inicio::getUrl())->assertOk()
            ->assertSee('Vas <strong>4º</strong> de 4', escape: false)
            ->assertSee('Toni Salgado · tú');
    }

    public function test_el_ranking_completo_de_una_seccion_tiene_lista_cuadro_y_ultima_manga(): void
    {
        $this->actingAs(User::where('email', 'socio@plica.test')->firstOrFail());
        $orilla = Seccion::where('nombre', 'Orilla')->firstOrFail();

        $this->get(RankingSeccion::getUrl(['seccion' => $orilla->id]))->assertOk()
            ->assertSee('Atrás')
            ->assertSee('Ranking Orilla')
            ->assertSee('Clasificación general')
            ->assertSee('Toni S.') // en el cuadro, con el nombre abreviado para el móvil
            ->assertSee('ganador de la manga')
            ->assertSee('1ª Manga')
            ->assertSee('Última manga')
            ->assertSee('href="https://wa.me/?text=', escape: false);

        // Una sección de otro club no existe para este socio.
        $otro = Club::create(['nombre' => 'Otro', 'slug' => 'otro']);
        $ajena = Seccion::create(['club_id' => $otro->id, 'nombre' => 'Ajena']);
        $this->get(RankingSeccion::getUrl(['seccion' => $ajena->id]))->assertNotFound();
    }

    public function test_la_clasificacion_completa_de_una_manga_resalta_al_socio(): void
    {
        $this->actingAs(User::where('email', 'socio@plica.test')->firstOrFail());
        $manga = Manga::where('estado', Manga::ESTADO_CELEBRADA)->orderBy('fecha')->firstOrFail();

        $this->get(ClasificacionManga::getUrl(['manga' => $manga->id]))->assertOk()
            ->assertSee('Atrás')
            ->assertSee($manga->nombre)
            ->assertSee('Mario López · tú')
            ->assertSee('Toni Salgado')
            ->assertSee('Ranking de Orilla')
            ->assertSee('Pieza mayor:');

        $otro = Club::create(['nombre' => 'Otro', 'slug' => 'otro']);
        $temporada = Temporada::create(['club_id' => $otro->id, 'nombre' => 'T', 'activa' => true]);
        $seccionAjena = Seccion::create(['club_id' => $otro->id, 'nombre' => 'Ajena']);
        $ajena = Manga::create(['temporada_id' => $temporada->id, 'seccion_id' => $seccionAjena->id, 'nombre' => 'Ajena', 'fecha' => today()]);
        $this->get(ClasificacionManga::getUrl(['manga' => $ajena->id]))->assertNotFound();
    }

    public function test_el_podio_lleva_medallas_y_barras_en_los_dos_paneles(): void
    {
        $this->actingAs(User::where('email', 'socio@plica.test')->firstOrFail());
        $this->get(Inicio::getUrl())->assertOk()->assertSee('plica-pos p1')->assertSee('plica-barra');

        $this->flushSession();
        $this->actingAs(User::where('email', 'admin@plica.test')->firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        // El admin ve la MISMA página que el público (cuadro manga a manga), con pestañas de sección encima.
        $this->get('/admin/ranking')->assertOk()->assertSee('Clasificación general')->assertSee('ganador de la manga');
    }
}
