<?php

namespace Tests\Feature;

use App\Filament\App\Auth\Perfil;
use App\Filament\Resources\Socios\Pages\EditSocio;
use App\Filament\Resources\Socios\Pages\ListSocios;
use App\Models\Socio;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Foto y número de licencia federativa del socio: los pone el admin en la
 * ficha o el socio en su perfil. La foto se guarda cuadrada y pequeña en
 * WebP y, de momento, solo se ve en el listado de socios del admin.
 */
class FotoYLicenciaSocioTest extends TestCase
{
    use RefreshDatabase;

    private Socio $paco;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        Storage::fake('public');
        $this->paco = Socio::where('nombre', 'Paco Jiménez')->firstOrFail();
    }

    private function comoAdmin(): void
    {
        $this->actingAs(User::where('email', 'admin@plica.test')->firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_el_admin_pone_foto_y_licencia_en_la_ficha_y_las_ve_en_el_listado(): void
    {
        $this->comoAdmin();

        Livewire::test(EditSocio::class, ['record' => $this->paco->getRouteKey()])
            ->assertFormFieldExists('foto')
            ->assertFormFieldExists('licencia')
            ->fillForm(['foto' => UploadedFile::fake()->image('paco.jpg', 900, 600), 'licencia' => ' M-12345 '])
            ->call('save')
            ->assertHasNoFormErrors();

        $paco = $this->paco->fresh();
        $this->assertSame('M-12345', $paco->licencia);
        $this->assertStringStartsWith('socios/', $paco->foto);
        $this->assertStringEndsWith('.webp', $paco->foto);
        Storage::disk('public')->assertExists($paco->foto);

        // WebP cuadrada de 400: el 900×600 se recorta al centro (600×600) y se reduce.
        $bytes = Storage::disk('public')->get($paco->foto);
        $this->assertSame('WEBP', substr($bytes, 8, 4));
        $this->assertSame([400, 400], array_slice(getimagesizefromstring($bytes), 0, 2));

        // El listado la enseña, con la licencia; el resto llevan la silueta.
        Livewire::test(ListSocios::class)
            ->assertSee($paco->fotoUrl())
            ->assertSee('Lic. M-12345')
            ->assertSee('img/socio.svg');
    }

    public function test_al_cambiar_o_quitar_la_foto_el_archivo_viejo_no_se_queda(): void
    {
        $this->comoAdmin();

        Livewire::test(EditSocio::class, ['record' => $this->paco->getRouteKey()])
            ->fillForm(['foto' => UploadedFile::fake()->image('uno.jpg', 300, 300)])
            ->call('save')
            ->assertHasNoFormErrors();
        $primera = $this->paco->fresh()->foto;

        // Cambiarla es quitar la que hay y subir otra (como en la pantalla).
        Livewire::test(EditSocio::class, ['record' => $this->paco->getRouteKey()])
            ->fillForm(['foto' => []])
            ->fillForm(['foto' => UploadedFile::fake()->image('dos.png', 300, 300)])
            ->call('save')
            ->assertHasNoFormErrors();
        $segunda = $this->paco->fresh()->foto;
        $this->assertNotSame($primera, $segunda);
        Storage::disk('public')->assertMissing($primera);
        Storage::disk('public')->assertExists($segunda);

        Livewire::test(EditSocio::class, ['record' => $this->paco->getRouteKey()])
            ->fillForm(['foto' => null])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertNull($this->paco->fresh()->foto);
        Storage::disk('public')->assertMissing($segunda);

        // Borrar un socio (sin historial) borra su foto.
        $nuevo = Socio::create(['club_id' => $this->paco->club_id, 'nombre' => 'Efímero']);
        Storage::disk('public')->put('socios/efimero.webp', 'x');
        $nuevo->update(['foto' => 'socios/efimero.webp']);
        $nuevo->delete();
        Storage::disk('public')->assertMissing('socios/efimero.webp');
    }

    public function test_el_socio_pone_su_foto_y_su_licencia_desde_su_perfil(): void
    {
        $user = User::where('email', 'socio@plica.test')->firstOrFail();
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('app'));

        $this->get('/app/profile')->assertOk()->assertSee('Foto')->assertSee('licencia federativa');

        Livewire::test(Perfil::class)
            ->fillForm(['foto' => UploadedFile::fake()->image('yo.jpg', 500, 800), 'licencia' => 'M-777'])
            ->call('save')
            ->assertHasNoFormErrors();

        $socio = $user->socio->fresh();
        $this->assertSame('M-777', $socio->licencia);
        Storage::disk('public')->assertExists($socio->foto);
        $this->assertSame([400, 400], array_slice(getimagesizefromstring(Storage::disk('public')->get($socio->foto)), 0, 2));

        // Su nombre y su email siguen igual: solo tocó foto y licencia.
        $this->assertSame($user->name, $user->fresh()->name);
        $this->assertSame('socio@plica.test', $user->fresh()->email);

        // Al volver, el perfil enseña lo guardado.
        Livewire::test(Perfil::class)->assertFormSet(['licencia' => 'M-777', 'foto' => $socio->foto]);
    }

    public function test_la_foto_no_sale_de_momento_ni_en_rankings_ni_en_la_web_publica(): void
    {
        Storage::disk('public')->put('socios/paco.webp', 'x');
        $this->paco->update(['foto' => 'socios/paco.webp']);
        $url = $this->paco->fotoUrl();

        $seccion = $this->paco->seccions()->firstOrFail(); // la sección en la que compite Paco

        auth()->logout();
        $this->get('/c/cd-pesca-piloto')->assertOk()->assertSee('Paco Jiménez')->assertDontSee($url);
        $this->get('/c/cd-pesca-piloto/'.$seccion->slug)->assertOk()->assertSee('Paco Jiménez')->assertDontSee($url);

        $this->actingAs(User::where('email', 'socio@plica.test')->firstOrFail());
        $this->get('/app')->assertOk()->assertDontSee($url);
    }
}
