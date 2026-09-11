<?php

namespace Tests\Feature;

use App\Filament\Resources\Seccions\Pages\ListSeccions;
use App\Models\Manga;
use App\Models\Seccion;
use App\Models\Socio;
use App\Models\Temporada;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** La base de datos protege el historial aunque la aplicación falle. */
class IntegridadDatosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    public function test_un_socio_con_historial_no_puede_borrarse(): void
    {
        $socio = Socio::has('participacions')->firstOrFail();

        $this->expectException(QueryException::class);
        $socio->delete();
    }

    public function test_un_socio_sin_historial_si_puede_borrarse(): void
    {
        $socio = Socio::doesntHave('participacions')->firstOrFail();

        $socio->delete();
        $this->assertDatabaseMissing('socios', ['id' => $socio->id]);
    }

    public function test_una_temporada_con_mangas_no_puede_borrarse(): void
    {
        $temporada = Temporada::has('mangas')->firstOrFail();

        $this->expectException(QueryException::class);
        $temporada->delete();
    }

    public function test_solo_una_temporada_activa_por_club(): void
    {
        $original = Temporada::where('activa', true)->firstOrFail();

        $nueva = Temporada::create([
            'club_id' => $original->club_id,
            'nombre' => 'Temporada 2027',
            'activa' => true,
        ]);

        $this->assertFalse($original->fresh()->activa);
        $this->assertTrue($nueva->fresh()->activa);
        $this->assertSame(1, Temporada::where('club_id', $original->club_id)->where('activa', true)->count());
    }

    public function test_una_seccion_con_pesajes_no_ofrece_borrado(): void
    {
        // Aquí la BD no protege (seccion_id es nullOnDelete): borrar una sección
        // recolocaría su historial en «Sin sección». La protección vive en la UI.
        $this->actingAs(User::where('email', 'admin@plica.test')->firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $conHistorial = Seccion::has('participacions')->firstOrFail();
        $sinHistorial = Seccion::create(['club_id' => $conHistorial->club_id, 'nombre' => 'Recién creada']);
        $conManga = Seccion::create(['club_id' => $conHistorial->club_id, 'nombre' => 'Con calendario']);
        Manga::create([
            'temporada_id' => Temporada::where('club_id', $conHistorial->club_id)->firstOrFail()->id,
            'seccion_id' => $conManga->id,
            'nombre' => 'Manga de la sección',
            'fecha' => today()->addDays(7),
        ]);

        $componente = Livewire::test(ListSeccions::class)
            ->assertActionHidden(TestAction::make('delete')->table($conHistorial))
            ->assertActionHidden(TestAction::make('delete')->table($conManga))
            ->assertActionVisible(TestAction::make('delete')->table($sinHistorial))
            // La papelera no desaparece sin más: en las protegidas explica por qué.
            ->assertActionVisible(TestAction::make('protegida')->table($conHistorial))
            ->assertActionVisible(TestAction::make('protegida')->table($conManga))
            ->assertActionHidden(TestAction::make('protegida')->table($sinHistorial))
            ->mountAction(TestAction::make('protegida')->table($conHistorial));

        $accion = $componente->instance()->getMountedAction();
        $this->assertStringContainsString('no se puede borrar', (string) $accion->getModalHeading());
        $this->assertStringContainsString('el historial se conserva', (string) $accion->getModalDescription());
    }

    public function test_borrar_una_manga_vacia_si_esta_permitido(): void
    {
        $temporada = Temporada::firstOrFail();
        $manga = Manga::create([
            'temporada_id' => $temporada->id,
            'seccion_id' => Seccion::firstOrFail()->id,
            'nombre' => 'Manga errónea',
            'fecha' => today()->addDays(30),
        ]);

        $manga->delete();
        $this->assertDatabaseMissing('mangas', ['id' => $manga->id]);
    }
}
