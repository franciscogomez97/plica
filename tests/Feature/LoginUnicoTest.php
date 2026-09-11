<?php

namespace Tests\Feature;

use App\Filament\Auth\Login;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Un solo login para todos: cada uno acaba en su panel según su rol. */
class LoginUnicoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('app'));
    }

    public function test_el_login_del_panel_del_club_manda_al_login_unico(): void
    {
        $this->get('/admin/login')->assertRedirect('/app/login');

        $this->get('/app/login')->assertOk()
            ->assertSee('Entrar en Plica')
            ->assertSee('Socios y administradores del club, por la misma puerta.');
    }

    public function test_el_admin_entra_y_va_a_su_panel_y_el_socio_al_suyo(): void
    {
        Livewire::test(Login::class)
            ->fillForm(['email' => 'admin@plica.test', 'password' => 'plica2026'])
            ->call('authenticate')
            ->assertHasNoFormErrors()
            ->assertRedirect('/admin');
        $this->assertTrue(auth()->user()->isAdmin());

        auth()->logout();
        $this->flushSession();

        Livewire::test(Login::class)
            ->fillForm(['email' => 'socio@plica.test', 'password' => 'plica2026'])
            ->call('authenticate')
            ->assertHasNoFormErrors()
            ->assertRedirect('/app');
        $this->assertFalse(auth()->user()->isAdmin());
    }

    public function test_si_el_admin_venia_de_un_enlace_del_panel_acaba_en_ese_enlace(): void
    {
        // Sin sesión, /admin/mangas manda al login (del club, que reenvía al único) y recuerda a dónde iba.
        $this->get('/admin/mangas')->assertRedirect('/admin/login');
        $this->get('/admin/login')->assertRedirect('/app/login');

        Livewire::test(Login::class)
            ->fillForm(['email' => 'admin@plica.test', 'password' => 'plica2026'])
            ->call('authenticate')
            ->assertRedirectContains('/admin/mangas');
    }

    public function test_la_web_publica_tiene_una_sola_puerta(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('Entrar')
            ->assertDontSee('Acceso clubes')
            ->assertDontSee('Soy pescador');
    }

    public function test_un_socio_que_entra_en_admin_sigue_yendo_a_su_panel(): void
    {
        $this->actingAs(User::where('email', 'socio@plica.test')->firstOrFail());

        $this->get('/admin')->assertRedirect('/app');
    }
}
