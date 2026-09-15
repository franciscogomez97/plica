<?php

namespace Tests\Feature;

use App\Filament\Resources\Mangas\Pages\EditManga;
use App\Filament\Resources\Socios\Pages\ListSocios;
use App\Models\Club;
use App\Models\Equipo;
use App\Models\Manga;
use App\Models\Seccion;
use App\Models\Socio;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

/**
 * El historial de competición no se reescribe por accidente: un socio cuyo
 * equipo ha pesado no se borra (se da de baja), y la sección de una manga con
 * pesajes no se cambia.
 */
class ProteccionesHistorialTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private Seccion $embarcacion;

    private Equipo $lucios;

    private Manga $manga;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        $this->club = Club::where('slug', 'cd-pesca-piloto')->firstOrFail();
        $this->embarcacion = Seccion::where('nombre', 'Embarcación')->firstOrFail();
        $this->embarcacion->update(['modalidad' => Seccion::MODALIDAD_EQUIPOS]);
        $temporada = $this->club->temporadaActiva();
        $this->club->altaDeEquipos('Los Lucios: Chema Ortiz / Luis Barranco', $this->embarcacion, $temporada);
        $this->lucios = Equipo::where('nombre', 'Los Lucios')->firstOrFail();
        $this->manga = Manga::create(['temporada_id' => $temporada->id, 'seccion_id' => $this->embarcacion->id, 'nombre' => 'Manga de barcos', 'fecha' => today(), 'estado' => Manga::ESTADO_CELEBRADA]);
        $this->manga->participacions()->create(['equipo_id' => $this->lucios->id, 'seccion_id' => $this->embarcacion->id])->capturas()->create(['piezas' => 1, 'peso_gramos' => 4000]);

        $this->actingAs(User::where('email', 'admin@plica.test')->firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_un_socio_cuyo_equipo_ha_pesado_no_se_borra_solo_se_da_de_baja(): void
    {
        // Chema no tiene pesajes propios (la plica es del barco), pero sí historial.
        $chema = Socio::where('nombre', 'Chema Ortiz')->firstOrFail();
        $this->assertSame(0, $chema->participacions()->count());
        $this->assertTrue($chema->tieneHistorial());

        Livewire::test(ListSocios::class)
            ->assertTableActionHidden('delete', $chema)
            ->assertTableActionVisible('protegido', $chema)
            ->callTableAction('protegido', $chema);
        $this->assertFalse($chema->fresh()->activo, 'se da de baja');
        $this->assertNotNull(Socio::find($chema->id));
        $this->assertSame(['Chema Ortiz', 'Luis Barranco'], $this->lucios->fresh()->socios->pluck('nombre')->all(), 'el equipo ganador sigue siendo el que era');

        // Y aunque alguien lo intente por código, tampoco.
        $this->expectException(LogicException::class);
        $chema->delete();
    }

    public function test_un_socio_sin_historial_si_se_borra(): void
    {
        $sinHistorial = Socio::create(['club_id' => $this->club->id, 'nombre' => 'Recién Llegado']);
        Livewire::test(ListSocios::class)
            ->assertTableActionVisible('delete', $sinHistorial)
            ->assertTableActionHidden('protegido', $sinHistorial)
            ->callTableAction('delete', $sinHistorial);
        $this->assertNull(Socio::find($sinHistorial->id));
    }

    public function test_la_seccion_de_una_manga_con_pesajes_no_se_cambia(): void
    {
        $orilla = Seccion::where('nombre', 'Orilla')->firstOrFail();

        $this->get("/admin/mangas/{$this->manga->id}/edit")->assertOk()->assertSee('ya tiene pesajes: su sección no se puede cambiar');
        Livewire::test(EditManga::class, ['record' => $this->manga->getRouteKey()])
            ->fillForm(['seccion_id' => $orilla->id, 'nombre' => 'Manga de barcos (editada)'])
            ->call('save')
            ->assertHasNoFormErrors();
        $manga = $this->manga->fresh();
        $this->assertSame($this->embarcacion->id, $manga->seccion_id, 'la sección no cambia');
        $this->assertSame('Manga de barcos (editada)', $manga->nombre, 'lo demás sí');

        // Una manga sin pesajes cambia de sección sin problema.
        $programada = Manga::create(['temporada_id' => $this->club->temporadaActiva()->id, 'seccion_id' => $this->embarcacion->id, 'nombre' => 'Programada', 'fecha' => today()->addWeek()]);
        Livewire::test(EditManga::class, ['record' => $programada->getRouteKey()])
            ->fillForm(['seccion_id' => $orilla->id])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame($orilla->id, $programada->fresh()->seccion_id);
    }
}
