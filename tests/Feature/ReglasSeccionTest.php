<?php

namespace Tests\Feature;

use App\Filament\App\Pages\RankingSeccion;
use App\Filament\Resources\Seccions\Pages\EditSeccion;
use App\Models\Seccion;
use App\Models\Temporada;
use App\Models\User;
use App\Services\Scoring;
use Database\Seeders\DemoSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Las reglas de una sección se explican igual en el formulario, el listado y los rankings. */
class ReglasSeccionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    public function test_las_reglas_se_cuentan_en_una_frase(): void
    {
        $this->assertSame(
            'Cada manga la gana quien más peso saca. El ranking suma el peso de todas las mangas. Si empatan, gana quien más piezas saque; si siguen igual, comparten puesto.',
            Seccion::resumenReglasDe(Seccion::CRITERIO_PESO),
        );

        $this->assertSame(
            'Cada manga la gana quien más centímetros suma (un pez por línea). El ranking suma los centímetros de todas las mangas. No cuenta la peor manga de cada socio. Si empatan, gana quien más piezas saque; si siguen igual, comparten puesto.',
            Seccion::resumenReglasDe(Seccion::CRITERIO_MEDIDA, descartes: 1),
        );

        $this->assertSame(
            'Cada manga la gana quien más piezas saca. El ranking suma las piezas de todas las mangas. No cuentan las 2 peores mangas de cada socio. Cada manga a la que se va suma además 10 puntos de asistencia. Si empatan, gana quien más peso sume; si siguen igual, comparten puesto.',
            Seccion::resumenReglasDe(Seccion::CRITERIO_PIEZAS, puntosParticipacion: 10, descartes: 2),
        );

        $this->assertSame(
            'Cada manga la gana quien más peso saca. El ranking suma los puestos de cada manga: gana quien menos suma. No ir a una manga cuesta el último puesto de esa manga más uno. Si empatan, gana la pieza mayor; si siguen igual, comparten puesto.',
            Seccion::resumenReglasDe(Seccion::CRITERIO_PESO, sistema: Seccion::SISTEMA_PUESTOS, desempate: Seccion::DESEMPATE_PIEZA_MAYOR),
        );
    }

    /** «Número de socios»: solo informativo, se guarda y se ve en el listado; no toca el cálculo. */
    public function test_el_numero_de_socios_se_guarda_y_se_ve_pero_no_cambia_nada(): void
    {
        $admin = User::where('email', 'admin@plica.test')->firstOrFail();
        $orilla = Seccion::where('nombre', 'Orilla')->firstOrFail();
        $antes = Scoring::rankingTemporada(Temporada::where('activa', true)->firstOrFail())->firstWhere('nombre', 'Orilla')->filas->pluck('puntos')->all();

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(EditSeccion::class, ['record' => $orilla->getRouteKey()])
            ->fillForm(['numero_socios' => 47])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(47, $orilla->fresh()->numero_socios);
        $this->get('/admin/seccions')->assertOk()->assertSee('47 socios');
        $this->assertSame($antes, Scoring::rankingTemporada(Temporada::where('activa', true)->firstOrFail())->firstWhere('nombre', 'Orilla')->filas->pluck('puntos')->all());
    }

    public function test_el_formulario_y_los_rankings_ensenan_las_reglas(): void
    {
        $admin = User::where('email', 'admin@plica.test')->firstOrFail();
        $orilla = Seccion::where('nombre', 'Orilla')->firstOrFail();
        $orilla->update(['descartes' => 1]);

        $this->actingAs($admin)->get('/admin/seccions/create')->assertOk()->assertSee('Así puntúa esta sección');
        $this->actingAs($admin)->get("/admin/seccions/{$orilla->id}/edit")->assertOk()->assertSee('No cuenta la peor manga de cada socio.');
        $this->actingAs($admin)->get('/admin/ranking')->assertOk()->assertSee('No cuenta la peor manga de cada socio.');

        // Sesión limpia: el panel comprueba el hash de contraseña guardado en sesión al cambiar de usuario.
        $this->flushSession();
        // El socio ve las reglas en el ranking completo de la sección (el Inicio es solo un resumen).
        $socio = User::where('email', 'socio@plica.test')->firstOrFail();
        Filament::setCurrentPanel(Filament::getPanel('app'));
        $this->actingAs($socio)->get(RankingSeccion::getUrl(['seccion' => $orilla->id]))
            ->assertOk()->assertSee('No cuenta la peor manga de cada socio.');

        auth()->logout();
        $this->flushSession();
        $this->get('/c/cd-pesca-piloto')->assertOk()->assertSee('No cuenta la peor manga de cada socio.');
    }
}
