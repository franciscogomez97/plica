<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Temporada;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Alta de un club nuevo y primeros pasos, incluido el backfill de mangas pasadas. */
class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_comando_crea_club_temporada_y_admin(): void
    {
        $this->artisan('plica:club', [
            'nombre' => 'SD Pescadores del Tajo',
            'admin_email' => '  PRESI@Tajo.ES ',
            '--localidad' => 'Toledo',
        ])->assertSuccessful();

        $club = Club::where('slug', 'sd-pescadores-del-tajo')->firstOrFail();
        $this->assertSame('Toledo', $club->localidad);
        $this->assertNotNull($club->temporadaActiva());

        $admin = User::where('email', 'presi@tajo.es')->firstOrFail();
        $this->assertTrue($admin->isAdmin());
        $this->assertSame($club->id, $admin->club_id);
    }

    public function test_slug_unico_y_email_duplicado_rechazado(): void
    {
        $this->artisan('plica:club', ['nombre' => 'CD Anzuelo', 'admin_email' => 'a@anzuelo.es'])->assertSuccessful();
        $this->artisan('plica:club', ['nombre' => 'CD Anzuelo', 'admin_email' => 'b@anzuelo.es'])->assertSuccessful();
        $this->assertNotNull(Club::where('slug', 'cd-anzuelo-2')->first());

        $this->artisan('plica:club', ['nombre' => 'CD Otro', 'admin_email' => 'a@anzuelo.es'])->assertFailed();
    }

    public function test_un_club_nuevo_puede_rellenar_mangas_pasadas_y_sale_el_ranking(): void
    {
        $this->artisan('plica:club', ['nombre' => 'CD Retroactivo', 'admin_email' => 'admin@retro.es'])->assertSuccessful();

        $admin = User::where('email', 'admin@retro.es')->firstOrFail();
        $this->actingAs($admin);

        $club = $admin->club;
        $temporada = $club->temporadaActiva();

        $socios = collect(['Pepe', 'Juan', 'Marta'])
            ->map(fn (string $n) => $club->socios()->create(['nombre' => $n]));

        // Manga de hace un mes: nace "por gestionar" automáticamente.
        $manga = \App\Models\Manga::create([
            'temporada_id' => $temporada->id,
            'nombre' => '1ª Manga',
            'fecha' => today()->subMonth(),
        ]);
        $this->assertTrue($manga->pendienteDeGestion());

        // Asistencia + peces + celebrada.
        $manga->sincronizarAsistencia($socios->pluck('id')->all());
        $pesos = ['Pepe' => 2500, 'Juan' => 4800, 'Marta' => 0];
        foreach ($manga->participacions()->with('socio')->get() as $p) {
            if ($pesos[$p->socio->nombre] > 0) {
                $p->capturas()->create(['piezas' => 2, 'peso_gramos' => $pesos[$p->socio->nombre]]);
            }
        }
        $manga->update(['estado' => \App\Models\Manga::ESTADO_CELEBRADA]);

        // El ranking sale solo, ordenado y sin ranking general.
        $ranking = \App\Services\Scoring::rankingTemporada($temporada);
        $filas = $ranking->firstWhere('nombre', 'Sin sección')->filas;
        $this->assertSame('Juan', $filas[0]->socio->nombre);
        $this->assertSame(4800, $filas[0]->puntos);
        $this->assertFalse($manga->fresh()->pendienteDeGestion());
    }

    public function test_el_admin_puede_abrir_su_perfil_para_cambiar_contrasena(): void
    {
        $this->seed(DemoSeeder::class);

        $admin = User::where('email', 'admin@plica.test')->firstOrFail();
        $this->actingAs($admin)->get('/admin/profile')->assertOk();
    }

    public function test_el_socio_puede_abrir_su_perfil_para_cambiar_contrasena(): void
    {
        $this->seed(DemoSeeder::class);

        $socio = User::where('email', 'socio@plica.test')->firstOrFail();
        $this->actingAs($socio)->get('/app/profile')->assertOk();
    }

    public function test_un_socio_con_cuenta_puede_ser_promovido_a_admin(): void
    {
        $this->seed(DemoSeeder::class);

        $socio = \App\Models\Socio::whereNotNull('user_id')->firstOrFail();
        $this->assertFalse($socio->user->isAdmin());

        // La acción de la tabla ejecuta exactamente esto:
        $socio->user->update(['role' => User::ROLE_ADMIN]);

        $this->actingAs($socio->user->fresh())->get('/admin')->assertOk();
    }
}
