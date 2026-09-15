<?php

namespace Tests\Feature;

use App\Filament\Resources\Seccions\Pages\CreateSeccion;
use App\Filament\Resources\Seccions\Pages\EditSeccion;
use App\Models\Seccion;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El formulario de la sección no engaña: quien elige «Federación» se lleva el
 * reglamento de la federación (revisión del 15 de septiembre de 2026), las
 * ausencias por puestos se eligen sin hablar de ceros, y el resumen va con
 * las opciones.
 */
class FormularioSeccionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        $this->actingAs(User::where('email', 'admin@plica.test')->firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_elegir_federacion_pone_los_valores_de_la_federacion(): void
    {
        // Una sección nueva de peso, «suma lo pescado»: empate por piezas, general comparten.
        $componente = Livewire::test(CreateSeccion::class)
            ->fillForm(['nombre' => 'Bass', 'criterio' => Seccion::CRITERIO_PESO])
            ->assertFormSet(['sistema_puntuacion' => Seccion::SISTEMA_ACUMULADO, 'desempate' => Seccion::DESEMPATE_PIEZAS]);

        // Pulsar «Federación»: promedio en la manga, gramos en la general, bolo por la media, ausencias descartables.
        $componente->fillForm(['sistema_puntuacion' => Seccion::SISTEMA_PUESTOS])
            ->assertFormSet([
                'desempate' => Seccion::DESEMPATE_PROMEDIO,
                'desempate_general' => Seccion::DESEMPATE_PESO,
                'bolo' => Seccion::BOLO_MEDIA,
                'descartes_ausencias' => true,
                'ausencia_fija' => 0,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $bass = Seccion::where('nombre', 'Bass')->firstOrFail();
        $this->assertSame(Seccion::DESEMPATE_PROMEDIO, $bass->desempate);
        $this->assertSame(Seccion::DESEMPATE_PESO, $bass->desempate_general);
        $this->assertSame(0, $bass->puntos_no_asistencia, '«el último más uno» se guarda como 0');
        $this->assertStringContainsString('se reparten el promedio', $bass->resumenReglas());
        $this->assertStringContainsString('gana quien más peso haya sacado en el año', $bass->resumenReglas());

        // Y vuelta a «suma lo pescado»: sus valores de siempre.
        Livewire::test(EditSeccion::class, ['record' => $bass->getRouteKey()])
            ->fillForm(['sistema_puntuacion' => Seccion::SISTEMA_ACUMULADO])
            ->assertFormSet(['desempate' => Seccion::DESEMPATE_PIEZAS, 'desempate_general' => Seccion::DESEMPATE_COMPARTIDO, 'descartes_ausencias' => false])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame(Seccion::DESEMPATE_PIEZAS, $bass->fresh()->desempate);
    }

    public function test_en_medida_la_federacion_desempata_la_general_por_centimetros(): void
    {
        Livewire::test(CreateSeccion::class)
            ->fillForm(['nombre' => 'Lucio', 'criterio' => Seccion::CRITERIO_MEDIDA, 'sistema_puntuacion' => Seccion::SISTEMA_PUESTOS])
            ->assertFormSet(['desempate_general' => Seccion::DESEMPATE_GENERAL_MEDIDA]);
    }

    public function test_la_ausencia_por_puestos_se_elige_sin_hablar_de_ceros(): void
    {
        $orilla = Seccion::where('nombre', 'Orilla')->firstOrFail();

        // Por defecto, «el último puesto de esa manga más uno»: en la base, 0.
        Livewire::test(EditSeccion::class, ['record' => $orilla->getRouteKey()])
            ->fillForm(['sistema_puntuacion' => Seccion::SISTEMA_PUESTOS])
            ->assertSee('Quien no va a una manga se lleva')
            ->assertSee('El último puesto de esa manga más uno')
            ->assertSee('Un número fijo de puntos')
            ->assertFormFieldIsHidden('puntos_no_asistencia')
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame(0, $orilla->fresh()->puntos_no_asistencia);
        $this->assertStringContainsString('No ir a una manga cuesta el último puesto de esa manga más uno.', $orilla->fresh()->resumenReglas());

        // Un número fijo: aparece la casilla y se guarda; al reabrir, la opción sale marcada.
        Livewire::test(EditSeccion::class, ['record' => $orilla->getRouteKey()])
            ->fillForm(['ausencia_fija' => 1, 'puntos_no_asistencia' => 48])
            ->assertFormFieldIsVisible('puntos_no_asistencia')
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame(48, $orilla->fresh()->puntos_no_asistencia);
        Livewire::test(EditSeccion::class, ['record' => $orilla->getRouteKey()])
            ->assertFormSet(['ausencia_fija' => 1, 'puntos_no_asistencia' => 48]);

        // De vuelta a «el último más uno», el 48 desaparece.
        Livewire::test(EditSeccion::class, ['record' => $orilla->getRouteKey()])
            ->fillForm(['ausencia_fija' => 0])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame(0, $orilla->fresh()->puntos_no_asistencia);
    }

    public function test_los_textos_van_al_grano_y_el_resumen_esta_con_las_opciones(): void
    {
        $html = $this->get('/admin/seccions/create')->assertOk()->getContent();

        foreach (['Número de socios', 'Nadie:', '0 = ', 'pescaron + 1', 'Lo habitual', 'Captura y suelta'] as $texto) {
            $this->assertStringNotContainsString($texto, $html, "no debe salir «{$texto}»");
        }
        $this->assertStringContainsString('Comparten el puesto', $html);
        Livewire::test(CreateSeccion::class)
            ->fillForm(['sistema_puntuacion' => Seccion::SISTEMA_PUESTOS])
            ->assertSee('Pescaron 21 y fueron 29: 25,5 puntos')
            ->assertDontSee('pescaron + 1');

        // El resumen «Así puntúa» va dentro del bloque del ranking, antes de los socios (que van al final).
        $resumen = strpos($html, 'Así puntúa esta sección');
        $socios = strpos($html, 'Socios de la sección');
        $sistema = strpos($html, 'El ranking de la temporada');
        $this->assertGreaterThan($sistema, $resumen);
        $this->assertLessThan($socios, $resumen);

        // Y se queda pegada arriba: el sticky va en el envoltorio del campo (el contenido no tendría dónde pegarse).
        $this->assertStringContainsString('plica-resumen-fijo', $html);
        $this->assertMatchesRegularExpression('/class="[^"]*plica-resumen-fijo[^"]*"[^>]*>(?:(?!<\/div>).)*Así puntúa esta sección/s', $html, 'la clase está en un envoltorio que contiene la etiqueta');
        $this->assertStringContainsString('.plica-resumen-fijo { position: sticky;', $html);
    }
}
