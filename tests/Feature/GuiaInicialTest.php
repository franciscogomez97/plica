<?php

namespace Tests\Feature;

use App\Filament\Widgets\GuiaInicialWidget;
use App\Models\Manga;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** La guía de primeros pasos: aparece a los nuevos, se tacha sola y sabe retirarse. */
class GuiaInicialTest extends TestCase
{
    use RefreshDatabase;

    private function adminNuevo(): User
    {
        $this->artisan('plica:club', ['nombre' => 'CD Novato', 'admin_email' => 'nuevo@novato.es'])->assertSuccessful();

        return User::where('email', 'nuevo@novato.es')->firstOrFail();
    }

    public function test_un_admin_nuevo_ve_la_guia_con_su_progreso(): void
    {
        $admin = $this->adminNuevo();

        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertSee('Bienvenido, Admin')
            ->assertSee('Pon tu propia contraseña')
            ->assertSee('Crea tus secciones')
            // El paso informativo muestra su explicación aunque esté completado:
            ->assertSee('La temporada agrupa las mangas de un año');
    }

    /**
     * Todos los pasos pendientes se ven enteros, con explicación y botón, no solo
     * el «siguiente»: un admin nuevo tiene que poder ir a crear sus secciones
     * aunque no haya cambiado aún la contraseña.
     */
    public function test_todos_los_pasos_pendientes_llevan_su_explicacion_y_su_boton(): void
    {
        $this->actingAs($this->adminNuevo())->get('/admin')
            ->assertOk()
            ->assertSee('en el orden que quieras')
            ->assertSee('Cambiar mi contraseña')
            ->assertSee('Crear mis secciones')
            ->assertSee('Cada modalidad es una sección con sus reglas')
            ->assertSee('Añadir socios')
            ->assertSee('Pega la lista de nombres tal cual la tengas')
            ->assertSee('Crear mi primera manga')
            ->assertDontSee('guia-futuro');
    }

    public function test_los_pasos_hechos_se_pliegan(): void
    {
        $admin = $this->adminNuevo();
        $admin->club->seccions()->create(['nombre' => 'Bass orilla']);

        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertSee('Crea tus secciones')      // tachado, sin explicación ni botón
            ->assertDontSee('Crear mis secciones')
            ->assertSee('Añadir socios');          // los pendientes siguen enteros
    }

    public function test_los_pasos_se_tachan_solos(): void
    {
        $admin = $this->adminNuevo();
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $widget = Livewire::test(GuiaInicialWidget::class);
        $pasos = $widget->instance()->getPasos();
        $this->assertFalse($pasos[0]['hecho']); // contraseña sin cambiar
        $this->assertTrue($pasos[1]['hecho']);  // temporada ya creada por el comando
        $this->assertFalse($pasos[2]['hecho']); // sin secciones

        // El admin trabaja: cambia contraseña, crea sección, socios y manga.
        $admin->update(['password' => 'micontrasenya1']);
        $club = $admin->club;
        $seccion = $club->seccions()->create(['nombre' => 'Bass orilla']);
        $club->socios()->create(['nombre' => 'Paco']);
        Manga::create(['temporada_id' => $club->temporadaActiva()->id, 'seccion_id' => $seccion->id, 'nombre' => '1ª', 'fecha' => today()]);

        $pasos = Livewire::test(GuiaInicialWidget::class)->instance()->getPasos();
        $this->assertSame([], array_filter($pasos, fn ($p) => ! $p['hecho']), 'Todos los pasos deberían estar hechos');

        // Con todo hecho, la guía celebra y se puede cerrar.
        $this->actingAs($admin->fresh())->get('/admin')->assertOk()->assertSee('está en marcha');
        Livewire::test(GuiaInicialWidget::class)->call('omitir');
        $this->assertNotNull($admin->fresh()->guia_completada_at);
        $this->actingAs($admin->fresh())->get('/admin')->assertOk()->assertDontSee('está en marcha');
    }

    public function test_el_admin_de_la_demo_no_ve_la_guia_pero_si_el_resumen(): void
    {
        $this->seed(DemoSeeder::class);
        $admin = User::where('email', 'admin@plica.test')->firstOrFail();

        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertDontSee('Pon tu propia contraseña')
            ->assertSee('Tu club de un vistazo')
            ->assertSee('Socios activos');
    }

    public function test_el_socio_ve_su_bienvenida_solo_hasta_que_la_cierra(): void
    {
        $this->seed(DemoSeeder::class);
        $socio = User::where('email', 'socio@plica.test')->firstOrFail();

        // La demo lo marca como guiado: no la ve.
        $this->actingAs($socio)->get('/app')->assertOk()->assertDontSee('bienvenido a');

        // Un socio recién llegado sí la ve, con el nombre de su club.
        $socio->forceFill(['guia_completada_at' => null])->save();
        $this->actingAs($socio->fresh())->get('/app')
            ->assertOk()
            ->assertSee('bienvenido a '.$socio->club->nombre);
    }
}
