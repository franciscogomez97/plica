<?php

namespace Tests\Feature;

use App\Filament\Resources\Mangas\Pages\CreateManga;
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
