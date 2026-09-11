<?php

namespace Tests\Feature;

use App\Filament\Auth\Login;
use App\Mail\NuevaSolicitud;
use App\Models\Socio;
use App\Services\AntiSpam;
use Database\Seeders\DemoSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/** Auditoría del ciclo completo de cuentas: login, enlaces de acceso y fronteras. */
class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    public function test_login_tolera_mayusculas_y_espacios_en_el_email(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('app'));

        Livewire::test(Login::class)
            ->fillForm([
                'email' => '  SOCIO@PLICA.TEST ',
                'password' => 'plica2026',
            ])
            ->call('authenticate');

        $this->assertAuthenticated();
        $this->assertSame('socio@plica.test', auth()->user()->email);
    }

    public function test_login_con_contrasena_incorrecta_no_entra(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('app'));

        Livewire::test(Login::class)
            ->fillForm([
                'email' => 'socio@plica.test',
                'password' => 'contramala',
            ])
            ->call('authenticate')
            ->assertHasErrors();

        $this->assertGuest();
    }

    public function test_invitados_van_al_login(): void
    {
        $this->get('/admin')->assertRedirect();
        $this->get('/app')->assertRedirect();
    }

    public function test_el_email_del_acceso_se_guarda_normalizado(): void
    {
        $socio = Socio::whereNull('user_id')->firstOrFail();
        $url = $socio->accessUrl();

        $this->post($url, [
            'name' => "  {$socio->nombre}  ",
            'email' => '  NUEVO@Plica.TEST ',
            'password' => 'superclave1',
            'password_confirmation' => 'superclave1',
        ])->assertRedirect('/app');

        $this->assertAuthenticated();
        $this->assertSame('nuevo@plica.test', $socio->refresh()->user->email);
    }

    public function test_una_validacion_fallida_no_quema_el_enlace(): void
    {
        $socio = Socio::whereNull('user_id')->firstOrFail();
        $url = $socio->accessUrl();

        // Email ya usado por otra cuenta.
        $this->from($url)->post($url, [
            'name' => $socio->nombre,
            'email' => 'SOCIO@plica.test', // duplicado, aunque venga en mayúsculas
            'password' => 'superclave1',
            'password_confirmation' => 'superclave1',
        ])->assertSessionHasErrors('email');

        // Contraseña demasiado corta.
        $this->from($url)->post($url, [
            'name' => $socio->nombre,
            'email' => 'libre@plica.test',
            'password' => 'corta',
            'password_confirmation' => 'corta',
        ])->assertSessionHasErrors('password');

        // El enlace sigue vivo para intentarlo de nuevo.
        $this->assertNotNull($socio->refresh()->invite_token);
        $this->get($url)->assertOk()->assertSee('Crear mi cuenta');
    }

    public function test_socio_inactivo_no_puede_usar_su_enlace(): void
    {
        $socio = Socio::whereNull('user_id')->firstOrFail();
        $url = $socio->accessUrl();
        $socio->update(['activo' => false]);

        $this->get($url)->assertOk()->assertSee('ya no vale');
        $this->post($url, [
            'name' => 'X',
            'email' => 'x@x.es',
            'password' => 'superclave1',
            'password_confirmation' => 'superclave1',
        ])->assertRedirect(route('acceso.show', $socio->invite_token));
        $this->assertGuest();
    }

    public function test_doble_envio_del_formulario_no_revienta(): void
    {
        $socio = Socio::whereNull('user_id')->firstOrFail();
        $url = $socio->accessUrl();
        $token = $socio->invite_token;
        $datos = [
            'name' => $socio->nombre,
            'email' => 'doble@plica.test',
            'password' => 'superclave1',
            'password_confirmation' => 'superclave1',
        ];

        $this->post($url, $datos)->assertRedirect('/app');

        // Segundo envío con el token ya consumido: página amable, sin 404 ni error.
        $this->post($url, $datos)->assertRedirect(route('acceso.show', $token));
        $this->get(route('acceso.show', $token))->assertOk()->assertSee('ya no vale');
    }

    public function test_token_inventado_no_da_pistas_ni_errores(): void
    {
        $this->get('/acceso/token-que-no-existe-para-nada-123')
            ->assertOk()
            ->assertSee('ya no vale');
    }

    public function test_solicitud_de_la_landing_normaliza_el_email_y_avisa_por_correo(): void
    {
        Mail::fake();
        config(['plica.notificaciones_email' => 'gestion@plica.test']);

        $this->post('/solicitud', [
            'club_nombre' => 'CD Prueba',
            'email' => '  INFO@CdPrueba.ES ',
            // Como una persona: formulario pintado hace un rato (ver AntiSpamTest).
            AntiSpam::CAMPO_SELLO => Crypt::encryptString((string) now()->subSeconds(30)->timestamp),
        ])->assertRedirect();

        $this->assertDatabaseHas('solicituds', ['email' => 'info@cdprueba.es']);
        Mail::assertSent(NuevaSolicitud::class, 1);
    }
}
