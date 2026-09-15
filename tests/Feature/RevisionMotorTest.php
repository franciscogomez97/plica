<?php

namespace Tests\Feature;

use App\Filament\Resources\Seccions\Pages\EditSeccion;
use App\Models\Manga;
use App\Models\Participacion;
use App\Models\Seccion;
use App\Models\Socio;
use App\Models\User;
use App\Services\Scoring;
use Database\Seeders\DemoSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use LogicException;
use Tests\Support\Escenario;
use Tests\TestCase;

/**
 * Lo que dejó la revisión del 15 de septiembre de 2026: por puestos las mangas
 * van por fecha también; sumando lo pescado no ir no puede sumar; quién pesca no
 * se cambia con historial; y una participación es de un socio o de un equipo.
 */
class RevisionMotorTest extends TestCase
{
    use RefreshDatabase;

    public function test_por_puestos_a_igual_coste_se_descarta_la_manga_mas_antigua_por_fecha_no_por_guardado(): void
    {
        // Dos mangas con el mismo coste para Bea (bolo en las dos, mismo número de gente). La manga
        // guardada primero es la MÁS RECIENTE: sin orden por fecha se descartaría la equivocada.
        $escenario = Escenario::crear([
            'Ana' => [3000, 2000],
            'Bea' => ['bolo', 'bolo'],
            'Cai' => [1000, 1500],
        ], ['sistema_puntuacion' => Seccion::SISTEMA_PUESTOS, 'descartes' => 1, 'descartes_ausencias' => true], 'puestos-fecha');
        [$primera, $segunda] = $escenario->mangas;
        $primera->update(['fecha' => '2026-05-20']); // la guardada primero pasa a ser la más reciente
        $segunda->update(['fecha' => '2026-02-10']);

        $grupo = Scoring::rankingTemporada($escenario->temporada)->firstWhere('seccionId', $escenario->seccion->id);
        $cuadro = Scoring::cuadroSeccion($escenario->temporada, $escenario->seccion);
        $bea = $cuadro->filas->firstWhere('participante.nombre', 'Bea');

        $this->assertSame([$segunda->id], $bea->descartadas, 'se descarta la más antigua por fecha');
        $this->assertSame($grupo->filas->firstWhere('participante.nombre', 'Bea')->puntos, $bea->puntos, 'ranking y cuadro coinciden');
    }

    public function test_sumando_lo_pescado_no_ir_no_puede_sumar_puntos(): void
    {
        $this->seed(DemoSeeder::class);
        $this->actingAs(User::where('email', 'admin@plica.test')->firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $orilla = Seccion::where('nombre', 'Orilla')->firstOrFail();

        Livewire::test(EditSeccion::class, ['record' => $orilla->getRouteKey()])
            ->fillForm(['sistema_puntuacion' => Seccion::SISTEMA_ACUMULADO, 'puntos_no_asistencia' => 5])
            ->call('save')
            ->assertHasFormErrors(['puntos_no_asistencia']);

        Livewire::test(EditSeccion::class, ['record' => $orilla->getRouteKey()])
            ->fillForm(['sistema_puntuacion' => Seccion::SISTEMA_ACUMULADO, 'puntos_no_asistencia' => -5])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame(-5, $orilla->fresh()->puntos_no_asistencia);

        // Por puestos sí: ahí es el coste de no ir.
        Livewire::test(EditSeccion::class, ['record' => $orilla->getRouteKey()])
            ->fillForm(['sistema_puntuacion' => Seccion::SISTEMA_PUESTOS, 'puntos_no_asistencia' => 48])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame(48, $orilla->fresh()->puntos_no_asistencia);
    }

    public function test_quien_pesca_no_se_cambia_si_la_seccion_ya_tiene_pesajes_esta_temporada(): void
    {
        $this->seed(DemoSeeder::class);
        $this->actingAs(User::where('email', 'admin@plica.test')->firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // Orilla tiene pesajes en la demo: el radio está bloqueado y guardar no lo cambia.
        $orilla = Seccion::where('nombre', 'Orilla')->firstOrFail();
        $this->assertTrue($orilla->tieneHistorialEnTemporadaActiva());
        $this->get("/admin/seccions/{$orilla->id}/edit")->assertOk()->assertSee('ya tiene pesajes esta temporada');
        Livewire::test(EditSeccion::class, ['record' => $orilla->getRouteKey()])
            ->fillForm(['modalidad' => Seccion::MODALIDAD_EQUIPOS])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame(Seccion::MODALIDAD_INDIVIDUAL, $orilla->fresh()->modalidad);

        // Una sección sin pesajes sí cambia.
        $nueva = Seccion::create(['club_id' => $orilla->club_id, 'nombre' => 'Carpfishing']);
        $this->assertFalse($nueva->tieneHistorialEnTemporadaActiva());
        Livewire::test(EditSeccion::class, ['record' => $nueva->getRouteKey()])
            ->fillForm(['modalidad' => Seccion::MODALIDAD_EQUIPOS, 'tamano_equipo' => 3])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame(Seccion::MODALIDAD_EQUIPOS, $nueva->fresh()->modalidad);
    }

    public function test_una_participacion_es_de_un_socio_o_de_un_equipo_nunca_de_ninguno(): void
    {
        $this->seed(DemoSeeder::class);
        $manga = Manga::where('estado', Manga::ESTADO_CELEBRADA)->firstOrFail();

        $this->expectException(LogicException::class);
        $manga->participacions()->create(['seccion_id' => $manga->seccion_id]);
    }

    public function test_una_participacion_no_puede_ser_de_los_dos_a_la_vez(): void
    {
        $this->seed(DemoSeeder::class);
        $manga = Manga::where('estado', Manga::ESTADO_CELEBRADA)->firstOrFail();
        $seccion = $manga->seccion;
        $seccion->update(['modalidad' => Seccion::MODALIDAD_EQUIPOS]);
        $equipo = $seccion->equipos()->create(['temporada_id' => $manga->temporada_id, 'nombre' => 'X']);
        $socio = Socio::where('nombre', 'Chema Ortiz')->firstOrFail();

        $this->expectException(LogicException::class);
        Participacion::create(['manga_id' => $manga->id, 'socio_id' => $socio->id, 'equipo_id' => $equipo->id, 'seccion_id' => $seccion->id]);
    }
}
