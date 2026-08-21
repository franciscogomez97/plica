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
        foreach (['/admin', '/admin/socios', '/admin/mangas', '/admin/temporadas', '/admin/seccions', '/admin/seccions/create', '/admin/ranking'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_dashboard_admin_muestra_acciones_rapidas(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin')
            ->assertOk()
            ->assertSee('¿Qué quieres hacer?')
            ->assertSee('Nueva manga')
            ->assertSee('Consultar ranking');
    }

    public function test_el_admin_tiene_pagina_de_ranking_de_temporada(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/ranking')
            ->assertOk()
            ->assertSee('mangas celebradas')
            ->assertSee('Orilla')
            ->assertSee('Mario López'); // líder de Orilla en la demo
    }

    public function test_solicitudes_solo_para_el_superadmin(): void
    {
        $admin = $this->admin();

        // El dueño de la plataforma las ve…
        config(['plica.superadmin_email' => $admin->email]);
        $this->actingAs($admin)->get('/admin/solicituds')->assertOk();

        // …un admin de club normal, no.
        config(['plica.superadmin_email' => null]);
        $this->actingAs($admin)->get('/admin/solicituds')->assertForbidden();
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
            ->assertSee('Ranking')
            // El ranking enseña el valor que ordena, en su unidad
            // (Mario: 4.350 + 2.100 g en Orilla).
            ->assertSee('6,450 kg');
    }

    public function test_socio_en_admin_es_redirigido_a_su_panel(): void
    {
        $socio = User::where('email', 'socio@plica.test')->firstOrFail();
        $this->actingAs($socio)->get('/admin')->assertRedirect('/app');
    }

    public function test_enlace_de_acceso_crea_cuenta_y_muere_al_usarse(): void
    {
        $socioSinCuenta = Socio::whereNull('user_id')->firstOrFail();
        $url = $socioSinCuenta->accessUrl();

        $this->get($url)->assertOk()->assertSee($socioSinCuenta->nombre)->assertSee('Crear mi cuenta');

        $this->post($url, [
            'name' => $socioSinCuenta->nombre,
            'email' => 'nuevo@plica.test',
            'password' => 'superclave1',
            'password_confirmation' => 'superclave1',
        ])->assertRedirect('/app');

        $socioSinCuenta->refresh();
        $this->assertNotNull($socioSinCuenta->user_id);
        $this->assertSame('socio', $socioSinCuenta->user->role);
        $this->assertNull($socioSinCuenta->invite_token); // un solo uso

        // El enlace muerto ya no sirve para nada.
        auth()->logout();
        $this->get($url)->assertOk()->assertSee('ya no vale');
    }

    public function test_enlace_de_acceso_restablece_contrasena(): void
    {
        $socio = Socio::where('email', 'socio@plica.test')->firstOrFail();
        $url = $socio->accessUrl();

        $this->get($url)->assertOk()->assertSee('Hola de nuevo');

        $this->post($url, [
            'password' => 'clavenueva99',
            'password_confirmation' => 'clavenueva99',
        ])->assertRedirect('/app');

        $socio->refresh();
        $this->assertNull($socio->invite_token);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('clavenueva99', $socio->user->fresh()->password));
    }

    public function test_manga_pendiente_avisa_al_admin(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        // Sin mangas pasadas sin gestionar, no hay aviso.
        $this->assertSame(0, Manga::pendientesDeGestion()->count());
        $this->get('/admin')->assertOk()->assertDontSee('por gestionar');

        // Una manga de ayer sin cerrar dispara el aviso en el dashboard.
        Manga::create([
            'temporada_id' => \App\Models\Temporada::firstOrFail()->id,
            'nombre' => 'Manga de ayer',
            'fecha' => today()->subDay(),
            'estado' => Manga::ESTADO_PROGRAMADA,
        ]);

        $this->assertSame(1, Manga::pendientesDeGestion()->count());
        $this->get('/admin')->assertOk()->assertSee('por gestionar')->assertSee('Manga de ayer');
    }

    public function test_sincronizar_asistencia(): void
    {
        $this->actingAs($this->admin());

        $programada = Manga::where('estado', Manga::ESTADO_PROGRAMADA)->firstOrFail();
        $socios = Socio::orderBy('id')->limit(3)->get();
        $seccion = \App\Models\Seccion::where('nombre', 'Orilla')->firstOrFail();

        // Marcar dos asistentes crea sus participaciones.
        $resultado = $programada->sincronizarAsistencia([$socios[0]->id, $socios[1]->id], $seccion->id);
        $this->assertSame(2, $resultado['creadas']);
        $this->assertSame(2, $programada->participacions()->count());

        // Desmarcar a uno sin capturas lo elimina.
        $resultado = $programada->sincronizarAsistencia([$socios[0]->id], $seccion->id);
        $this->assertSame(1, $resultado['eliminadas']);
        $this->assertSame(1, $programada->participacions()->count());

        // Desmarcar a alguien CON capturas queda bloqueado, no se pierde nada.
        $celebrada = Manga::where('estado', Manga::ESTADO_CELEBRADA)->firstOrFail();
        $conCapturas = $celebrada->participacions()->whereHas('capturas')->with('socio')->firstOrFail();
        $resultado = $celebrada->sincronizarAsistencia([]);
        $this->assertContains($conCapturas->socio->nombre, $resultado['bloqueadas']);
        $this->assertDatabaseHas('participacions', ['id' => $conCapturas->id]);
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

    public function test_una_manga_de_seccion_apunta_la_asistencia_a_su_seccion(): void
    {
        $temporada = \App\Models\Temporada::firstOrFail();
        $seccion = \App\Models\Seccion::where('club_id', $temporada->club_id)->firstOrFail();
        $manga = Manga::create([
            'temporada_id' => $temporada->id,
            'seccion_id' => $seccion->id,
            'nombre' => 'Manga de sección',
            'fecha' => today(),
        ]);

        $socios = Socio::where('club_id', $temporada->club_id)->orderBy('id')->limit(2)->get();
        // Sin pasar sección (el desplegable ni aparece): manda la de la manga.
        $manga->sincronizarAsistencia($socios->pluck('id')->all());

        $this->assertSame(
            [$seccion->id, $seccion->id],
            $manga->participacions()->pluck('seccion_id')->all(),
        );
    }

    public function test_tabla_de_participaciones_renderiza_de_verdad(): void
    {
        // Los relation managers cargan lazy: un GET a la página NO renderiza
        // esta tabla. Este test la monta como componente Livewire real.
        $this->actingAs($this->admin());
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));

        $manga = Manga::where('estado', Manga::ESTADO_CELEBRADA)->firstOrFail();

        \Livewire\Livewire::test(\App\Filament\Resources\Mangas\RelationManagers\ParticipacionsRelationManager::class, [
            'ownerRecord' => $manga,
            'pageClass' => \App\Filament\Resources\Mangas\Pages\EditManga::class,
        ])
            ->assertSuccessful()
            ->assertSee('Marcar asistencia')
            ->assertSee($manga->participacions()->with('socio')->first()->socio->nombre);
    }
}
