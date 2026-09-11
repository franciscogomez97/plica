<?php

namespace Tests\Feature;

use App\Filament\Pages\Inicio;
use App\Filament\Resources\Mangas\MangaResource;
use App\Filament\Resources\Mangas\Pages\CreateManga;
use App\Filament\Resources\Seccions\Pages\CreateSeccion;
use App\Filament\Resources\Seccions\SeccionResource;
use App\Filament\Resources\Socios\SocioResource;
use App\Models\Club;
use App\Models\Manga;
use App\Models\Seccion;
use App\Models\Socio;
use App\Models\Temporada;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * «Atrás» es jerárquico (va al padre, no al historial) y crear una sección
 * encadena con crear su calendario de mangas.
 */
class NavegacionAtrasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        $this->actingAs(User::where('email', 'admin@plica.test')->firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function assertAtrasLlevaA(string $pagina, string $destino): void
    {
        $this->get($pagina)
            ->assertOk()
            ->assertSee('href="'.$destino.'" wire:navigate', escape: false);
    }

    public function test_atras_va_al_padre_y_nunca_al_historial(): void
    {
        $manga = Manga::where('estado', Manga::ESTADO_CELEBRADA)->firstOrFail();
        $socio = Socio::firstOrFail();
        $pesaje = MangaResource::getUrl('pesaje', ['record' => $manga]);

        $this->assertAtrasLlevaA('/admin/seccions/create', SeccionResource::getUrl());
        $this->assertAtrasLlevaA("/admin/socios/{$socio->id}/edit", SocioResource::getUrl());
        $this->assertAtrasLlevaA('/admin/mangas/create', MangaResource::getUrl());
        $this->assertAtrasLlevaA("/admin/mangas/{$manga->id}/pesaje", MangaResource::getUrl());
        $this->assertAtrasLlevaA("/admin/mangas/{$manga->id}/edit", $pesaje);
        $this->assertAtrasLlevaA("/admin/mangas/{$manga->id}/clasificacion", $pesaje);
        $this->assertAtrasLlevaA('/admin/profile', Inicio::getUrl());
    }

    public function test_crear_una_seccion_lleva_a_crear_sus_mangas_con_la_seccion_puesta(): void
    {
        $componente = Livewire::test(CreateSeccion::class)
            ->fillForm(['nombre' => 'Carpfishing', 'criterio' => Seccion::CRITERIO_PESO])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified('Sección creada. Ahora, las mangas de su calendario.');

        $seccion = Seccion::where('nombre', 'Carpfishing')->firstOrFail();
        $componente->assertRedirect(MangaResource::getUrl('create', ['seccion' => $seccion->id]));

        Livewire::withQueryParams(['seccion' => $seccion->id])
            ->test(CreateManga::class)
            ->assertSchemaStateSet(['seccion_id' => $seccion->id]);
    }

    public function test_una_seccion_de_otro_club_no_se_cuela_por_la_url(): void
    {
        $otro = Club::create(['nombre' => 'Otro club', 'slug' => 'otro-club']);
        $ajena = Seccion::create(['club_id' => $otro->id, 'nombre' => 'Ajena']);

        Livewire::withQueryParams(['seccion' => $ajena->id])
            ->test(CreateManga::class)
            ->assertSchemaStateSet(['seccion_id' => null]);
    }

    public function test_dos_secciones_con_el_mismo_nombre_no_se_pueden_crear(): void
    {
        Livewire::test(CreateSeccion::class)
            ->fillForm(['nombre' => 'Orilla', 'criterio' => Seccion::CRITERIO_PESO])
            ->call('create')
            ->assertHasFormErrors(['nombre' => 'unique']);

        $this->assertSame(1, Seccion::where('nombre', 'Orilla')->count());
    }

    public function test_el_calendario_se_mete_de_una_tacada_con_crear_y_anadir_otra(): void
    {
        $seccion = Seccion::where('nombre', 'Orilla')->firstOrFail();
        $temporada = Temporada::firstOrFail();

        $componente = Livewire::withQueryParams(['seccion' => $seccion->id])
            ->test(CreateManga::class)
            ->fillForm(['nombre' => '1ª de Orilla', 'fecha' => today()->addDays(7)->toDateString()])
            ->call('createAnother')
            ->assertHasNoFormErrors()
            // Temporada y sección se quedan puestas para la siguiente.
            ->assertSchemaStateSet(['seccion_id' => $seccion->id, 'temporada_id' => $temporada->id]);

        $componente
            ->fillForm(['nombre' => '2ª de Orilla', 'fecha' => today()->addDays(21)->toDateString()])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect(MangaResource::getUrl());

        $this->assertSame(
            [$seccion->id, $seccion->id],
            Manga::whereIn('nombre', ['1ª de Orilla', '2ª de Orilla'])->pluck('seccion_id')->all(),
        );
    }
}
