<?php

namespace Tests\Feature;

use App\Filament\Resources\Seccions\Pages\EditSeccion;
use App\Models\Manga;
use App\Models\Seccion;
use App\Models\Socio;
use App\Models\Temporada;
use App\Models\User;
use App\Services\Scoring;
use Database\Seeders\DemoSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Puntos por no ir: algunos clubes los dan (o los quitan) por cada manga a la
 * que un socio no va. Por defecto 0, y con 0 nada cambia para nadie.
 */
class NoAsistenciaTest extends TestCase
{
    use RefreshDatabase;

    private Seccion $orilla;

    private Temporada $temporada;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        $this->orilla = Seccion::where('nombre', 'Orilla')->firstOrFail();
        $this->temporada = Temporada::where('activa', true)->firstOrFail();

        // En la demo todos van a todo: Alberto Rey se pierde la primera manga de Orilla.
        $primera = Manga::where('seccion_id', $this->orilla->id)->where('estado', Manga::ESTADO_CELEBRADA)->orderBy('fecha')->firstOrFail();
        $primera->participacions()->where('socio_id', Socio::where('nombre', 'Alberto Rey')->firstOrFail()->id)->delete();
    }

    private function puntosOrilla(): array
    {
        return Scoring::rankingTemporada($this->temporada)
            ->firstWhere('nombre', 'Orilla')
            ->filas->mapWithKeys(fn (object $f) => [$f->socio->nombre => $f->puntos])->all();
    }

    public function test_por_defecto_es_cero_y_no_cambia_nada(): void
    {
        $this->assertSame(0, $this->orilla->puntos_no_asistencia);
        $this->assertStringNotContainsString('ausencia', $this->orilla->resumenReglas());

        $antes = $this->puntosOrilla();
        $this->orilla->update(['puntos_no_asistencia' => 0]);
        $this->assertSame($antes, $this->puntosOrilla());
    }

    public function test_cada_manga_no_pescada_suma_los_puntos_de_la_seccion(): void
    {
        // Orilla tiene dos mangas celebradas; Alberto fue solo a la segunda.
        $antes = $this->puntosOrilla();
        $mangas = Manga::where('seccion_id', $this->orilla->id)->where('estado', Manga::ESTADO_CELEBRADA)->count();
        $this->assertSame(2, $mangas);

        $this->orilla->update(['puntos_no_asistencia' => 100]);
        $despues = $this->puntosOrilla();

        foreach ($antes as $nombre => $puntos) {
            $socio = Socio::where('nombre', $nombre)->firstOrFail();
            $pescadas = $socio->participacions()->where('seccion_id', $this->orilla->id)->count();
            $this->assertSame($puntos + ($mangas - $pescadas) * 100, $despues[$nombre], $nombre);
        }

        $this->assertSame($antes['Alberto Rey'] + 100, $despues['Alberto Rey']);
        $this->assertSame($antes['Mario López'], $despues['Mario López']);

        // Alguien que no ha pescado ninguna manga sigue sin estar en el ranking.
        $this->assertArrayNotHasKey('Chema Ortiz', $despues);

        $this->assertStringContainsString('Cada ausencia suma 100 puntos.', $this->orilla->fresh()->resumenReglas());
    }

    public function test_en_negativo_resta(): void
    {
        $antes = $this->puntosOrilla();
        $this->orilla->update(['puntos_no_asistencia' => -50]);
        $despues = $this->puntosOrilla();

        $this->assertSame($antes['Alberto Rey'] - 50, $despues['Alberto Rey']);
        $this->assertSame($antes['Mario López'], $despues['Mario López']);
        $this->assertStringContainsString('Cada ausencia resta 50 puntos.', $this->orilla->fresh()->resumenReglas());
    }

    public function test_el_cuadro_manga_a_manga_lo_ensena_en_las_celdas_de_no_fue(): void
    {
        $this->orilla->update(['puntos_no_asistencia' => 100]);
        $cuadro = Scoring::cuadroSeccion($this->temporada, $this->orilla->fresh());
        $this->assertSame(100, $cuadro->puntosNoAsistencia);

        // Los puntos del cuadro son los mismos que los del ranking.
        $this->assertSame($this->puntosOrilla(), $cuadro->filas->mapWithKeys(fn (object $f) => [$f->socio->nombre => $f->puntos])->all());

        $this->actingAs(User::where('email', 'admin@plica.test')->firstOrFail());
        $this->get("/admin/ranking/{$this->orilla->id}")->assertOk()
            ->assertSee('No participó: +100 pts')
            ->assertSee('Cada ausencia suma 100 puntos');
        $this->get('/c/cd-pesca-piloto/orilla')->assertOk()->assertSee('No participó: +100 pts');
    }

    /** «Puntos por ausencia» en los dos sistemas: en «suma lo pescado» admite negativos (castigo); por puestos es lo que cuesta no ir. */
    public function test_se_configura_en_los_dos_sistemas(): void
    {
        $this->actingAs(User::where('email', 'admin@plica.test')->firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // Orilla suma lo pescado: un castigo de 200 por cada ausencia.
        Livewire::test(EditSeccion::class, ['record' => $this->orilla->getRouteKey()])
            ->assertFormFieldExists('puntos_participacion')
            ->assertFormFieldExists('puntos_no_asistencia')
            ->fillForm(['puntos_no_asistencia' => -200])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame(-200, $this->orilla->fresh()->puntos_no_asistencia);
        $this->assertStringContainsString('Cada ausencia resta 200 puntos.', $this->orilla->fresh()->resumenReglas());

        // Por puestos: lo que cuesta no ir, nunca negativo.
        Livewire::test(EditSeccion::class, ['record' => $this->orilla->getRouteKey()])
            ->fillForm(['sistema_puntuacion' => Seccion::SISTEMA_PUESTOS, 'puntos_no_asistencia' => 25])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame(25, $this->orilla->fresh()->puntos_no_asistencia);
        $this->assertStringContainsString('No ir a una manga cuesta 25 puntos', $this->orilla->fresh()->resumenReglas());
    }
}
