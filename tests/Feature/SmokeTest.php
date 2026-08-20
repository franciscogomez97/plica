<?php

namespace Tests\Feature;

use App\Models\Manga;
use App\Models\Socio;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    private function admin(): User
    {
        return User::where('email', 'admin@plica.test')->firstOrFail();
    }

    public function test_landing_y_club_publico_cargan(): void
    {
        $this->get('/')->assertOk()->assertSee('Solicita acceso');
        $this->get('/c/cd-pesca-piloto')->assertOk()->assertSee('Ranking')->assertSee('CD Pesca Piloto');
        $this->get('/c/no-existe')->assertNotFound();
    }

    public function test_solicitud_de_acceso_se_guarda(): void
    {
        $this->post('/solicitud', ['club_nombre' => 'CD Test', 'email' => 'test@test.es'])
            ->assertRedirect();
        $this->assertDatabaseHas('solicituds', ['club_nombre' => 'CD Test']);
    }

    public function test_panel_admin_carga_todas_las_secciones(): void
    {
        $admin = $this->admin();
        foreach (['/admin', '/admin/socios', '/admin/mangas', '/admin/temporadas', '/admin/seccions', '/admin/solicituds'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_clasificacion_de_manga_calcula_puestos(): void
    {
        $manga = Manga::where('estado', Manga::ESTADO_CELEBRADA)->orderBy('fecha')->firstOrFail();
        $this->actingAs($this->admin())
            ->get("/admin/mangas/{$manga->id}/clasificacion")
            ->assertOk()
            ->assertSee('1º')
            ->assertSee('Mario López') // 1º de Orilla por peso (4,350 kg)
            ->assertSee('Pato — Lucio')
            ->assertSee('Andrés Molina') // 1º de Pato por medida (120,5 cm)
            ->assertSee('120,5 cm');
    }

    public function test_edicion_de_manga_carga_con_participaciones(): void
    {
        $manga = Manga::where('estado', Manga::ESTADO_CELEBRADA)->firstOrFail();
        $this->actingAs($this->admin())->get("/admin/mangas/{$manga->id}/edit")->assertOk();
    }

    public function test_panel_socio_muestra_ranking_y_proximas_mangas(): void
    {
        $socio = User::where('email', 'socio@plica.test')->firstOrFail();
        $this->actingAs($socio)
            ->get('/app')
            ->assertOk()
            ->assertSee('Próximas mangas')
            ->assertSee('3ª Manga')
            ->assertSee('Ranking');
    }

    public function test_socio_no_puede_entrar_al_panel_admin(): void
    {
        $socio = User::where('email', 'socio@plica.test')->firstOrFail();
        $this->actingAs($socio)->get('/admin')->assertForbidden();
    }

    public function test_flujo_de_invitacion_completo(): void
    {
        $socioSinCuenta = Socio::whereNull('user_id')->firstOrFail();
        $url = $socioSinCuenta->inviteUrl();

        $this->get($url)->assertOk()->assertSee($socioSinCuenta->nombre);

        $this->post($url, [
            'name' => $socioSinCuenta->nombre,
            'email' => 'nuevo@plica.test',
            'password' => 'superclave1',
            'password_confirmation' => 'superclave1',
        ])->assertRedirect('/app');

        $socioSinCuenta->refresh();
        $this->assertNotNull($socioSinCuenta->user_id);
        $this->assertSame('socio', $socioSinCuenta->user->role);

        // Reutilizar la invitación no debe permitir crear otra cuenta.
        $this->get($url)->assertOk()->assertSee('ya se usó');
    }

    public function test_ranking_por_puestos_y_descartes_configurado_en_seccion(): void
    {
        $temporada = \App\Models\Temporada::firstOrFail();
        $orillaSeccion = \App\Models\Seccion::where('nombre', 'Orilla')->firstOrFail();
        $orillaSeccion->update(['sistema_puntuacion' => \App\Models\Seccion::SISTEMA_PUESTOS]);

        $orilla = \App\Services\Scoring::rankingTemporada($temporada)
            ->firstWhere('nombre', 'Orilla');

        // Sergio (2º+1º = 3 pts) gana a Mario (1º+3º = 4 pts).
        $this->assertSame('Sergio del Río', $orilla->filas[0]->socio->nombre);
        $this->assertSame(3, $orilla->filas[0]->puntos);
        $this->assertSame('Mario López', $orilla->filas[1]->socio->nombre);

        // Con 1 descarte solo cuenta la mejor manga: Mario (1º) empata a 1 con Sergio (1º),
        // desempate por peso acumulado -> Mario delante.
        $orillaSeccion->update(['descartes' => 1]);
        $orilla = \App\Services\Scoring::rankingTemporada($temporada)
            ->firstWhere('nombre', 'Orilla');
        $this->assertSame(1, $orilla->filas[0]->puntos);
        $this->assertSame('Mario López', $orilla->filas[0]->socio->nombre);

        // Y el resto de secciones no se ven afectadas: Pato sigue en acumulado por medida.
        $pato = \App\Services\Scoring::rankingTemporada($temporada)
            ->firstWhere('nombre', 'Pato — Lucio');
        $this->assertSame('Dani Cuesta', $pato->filas[0]->socio->nombre);
        $this->assertSame(1780, $pato->filas[0]->puntos);
    }

    public function test_puntos_de_participacion_por_seccion(): void
    {
        $temporada = \App\Models\Temporada::firstOrFail();
        \App\Models\Seccion::where('nombre', 'Orilla')->firstOrFail()
            ->update(['puntos_participacion' => 500]);

        $ranking = \App\Services\Scoring::rankingTemporada($temporada);

        // Orilla: Mario 6450 g + 2 mangas x 500 pts = 7450.
        $orilla = $ranking->firstWhere('nombre', 'Orilla');
        $this->assertSame(7450, $orilla->filas->firstWhere(fn ($f) => $f->socio->nombre === 'Mario López')->puntos);

        // Embarcación no tiene puntos de participación: Jorge se queda en sus 9840 g.
        $embarcacion = $ranking->firstWhere('nombre', 'Embarcación');
        $this->assertSame(9840, $embarcacion->filas[0]->puntos);
    }
}
