<?php

namespace Tests\Feature;

use App\Filament\Pages\MiClub;
use App\Models\Club;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** El logotipo del club: se sube en «Mi club», se guarda en WebP y se ve en todas partes. */
class LogoClubTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        Storage::fake('public');
        $this->actingAs(User::where('email', 'admin@plica.test')->firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_el_logo_se_convierte_a_webp_reducido_y_se_ve_en_paneles_y_web_publica(): void
    {
        $this->get('/admin/mi-club')->assertOk()->assertSee('Logotipo')->assertSee('Atrás');

        Livewire::test(MiClub::class)
            ->fillForm([
                'nombre' => 'CD Pesca Piloto',
                'localidad' => 'Madrid (Embalse de San Juan)',
                'logo' => UploadedFile::fake()->image('escudo.png', 1200, 800),
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $club = Club::where('slug', 'cd-pesca-piloto')->firstOrFail();
        $this->assertSame('Madrid (Embalse de San Juan)', $club->localidad);
        $this->assertStringEndsWith('.webp', $club->logo);
        Storage::disk('public')->assertExists($club->logo);

        // WebP de verdad, y no más de 512 px de lado.
        $bytes = Storage::disk('public')->get($club->logo);
        $this->assertSame('RIFF', substr($bytes, 0, 4));
        $this->assertSame('WEBP', substr($bytes, 8, 4));
        [$ancho, $alto] = getimagesizefromstring($bytes);
        $this->assertSame(512, $ancho);
        $this->assertSame(341, $alto);

        // El admin lo ve en su cabecera; el socio, en la suya y en el encabezado del Inicio.
        $this->get('/admin')->assertOk()->assertSee($club->logoUrl());

        $this->flushSession();
        $this->actingAs(User::where('email', 'socio@plica.test')->firstOrFail());
        $this->get('/app')->assertOk()->assertSee($club->logoUrl());

        // Y cualquiera, en la portada, en la sección y como imagen de la vista previa de WhatsApp.
        auth()->logout();
        $this->flushSession();
        $this->get('/c/cd-pesca-piloto')->assertOk()
            ->assertSee($club->logoUrl())
            ->assertSee('property="og:image" content="'.$club->logoUrl().'"', escape: false);
        // En la sección, la vista previa del enlace es la tarjeta del podio (que lleva el logo
        // pintado dentro); el logo sigue en la cabecera de la página.
        $this->get('/c/cd-pesca-piloto/orilla')->assertOk()
            ->assertSee('src="'.$club->logoUrl().'"', escape: false)
            ->assertSee('/orilla/podio.jpg?v=', escape: false);
    }

    public function test_al_cambiar_o_quitar_el_logo_el_archivo_viejo_no_se_queda_huerfano(): void
    {
        Livewire::test(MiClub::class)
            ->fillForm(['logo' => UploadedFile::fake()->image('uno.jpg', 300, 300)])
            ->call('save')
            ->assertHasNoFormErrors();
        $primero = Club::where('slug', 'cd-pesca-piloto')->firstOrFail()->logo;
        Storage::disk('public')->assertExists($primero);

        // Quitar el logo borra el archivo.
        Livewire::test(MiClub::class)
            ->fillForm(['logo' => null])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertNull(Club::where('slug', 'cd-pesca-piloto')->firstOrFail()->logo);
        Storage::disk('public')->assertMissing($primero);

        // Sin logo, la cabecera vuelve a ser solo el nombre del club.
        $this->get('/admin')->assertOk()->assertSee('CD Pesca Piloto')->assertDontSee('/storage/logos/');

        // Subir otro después: archivo nuevo, y solo ese.
        Livewire::test(MiClub::class)
            ->fillForm(['logo' => UploadedFile::fake()->image('dos.png', 300, 300)])
            ->call('save')
            ->assertHasNoFormErrors();
        $segundo = Club::where('slug', 'cd-pesca-piloto')->firstOrFail()->logo;
        $this->assertNotSame($primero, $segundo);
        Storage::disk('public')->assertExists($segundo);
        $this->assertCount(1, Storage::disk('public')->files('logos'));
    }

    public function test_un_archivo_que_no_es_imagen_se_rechaza(): void
    {
        Livewire::test(MiClub::class)
            ->fillForm(['logo' => UploadedFile::fake()->create('reglamento.pdf', 100, 'application/pdf')])
            ->call('save')
            ->assertHasFormErrors(['logo']);

        $this->assertNull(Club::where('slug', 'cd-pesca-piloto')->firstOrFail()->logo);
    }

    public function test_el_inicio_del_admin_tiene_el_boton_de_mi_club_y_el_socio_no_entra(): void
    {
        $this->get('/admin')->assertOk()->assertSee('Mi club')->assertSee(MiClub::getUrl());

        $this->flushSession();
        $this->actingAs(User::where('email', 'socio@plica.test')->firstOrFail());
        $this->get('/admin/mi-club')->assertRedirect('/app');
    }
}
