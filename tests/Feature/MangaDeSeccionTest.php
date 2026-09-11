<?php

namespace Tests\Feature;

use App\Filament\Resources\Mangas\Pages\CreateManga;
use App\Filament\Resources\Seccions\SeccionResource;
use App\Models\Manga;
use App\Models\Seccion;
use App\Models\Temporada;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** La sección se elige UNA vez, al crear la manga; el pesaje no vuelve a preguntarla. */
class MangaDeSeccionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        $this->actingAs(User::where('email', 'admin@plica.test')->firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    /** Un club recién creado no tiene secciones: crear una manga te manda a crear la primera. */
    public function test_sin_secciones_crear_una_manga_manda_a_crear_la_primera_seccion(): void
    {
        $this->artisan('plica:club', ['nombre' => 'Club Sin Secciones', 'admin_email' => 'nuevo@sinsecciones.es'])->assertSuccessful();
        $admin = User::where('email', 'nuevo@sinsecciones.es')->firstOrFail();

        $this->actingAs($admin)->get('/admin/mangas/create')
            ->assertRedirect(SeccionResource::getUrl('create'));

        // Con una sección ya creada, el formulario se abre normal.
        $admin->club->seccions()->create(['nombre' => 'Orilla']);
        $this->actingAs($admin)->get('/admin/mangas/create')->assertOk()->assertSee('Elige la sección');
    }

    public function test_crear_una_manga_con_seccion_desde_el_formulario_la_guarda_y_el_pesaje_no_la_pregunta(): void
    {
        $seccion = Seccion::where('nombre', 'Orilla')->firstOrFail();

        Livewire::test(CreateManga::class)
            ->fillForm([
                'temporada_id' => Temporada::firstOrFail()->id,
                'seccion_id' => $seccion->id,
                'nombre' => 'Manga de Orilla',
                'fecha' => today()->addDays(3)->toDateString(),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $manga = Manga::where('nombre', 'Manga de Orilla')->firstOrFail();
        $this->assertSame($seccion->id, $manga->seccion_id, 'El formulario no guardó la sección');

        $this->get("/admin/mangas/{$manga->id}/pesaje")
            ->assertOk()
            ->assertDontSee('pesaje-nueva-seccion')
            ->assertDontSee('Sin sección');
    }
}
