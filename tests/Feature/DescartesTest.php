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
 * Descartes: qué es «la peor manga» para quien faltó a alguna. El club elige
 * si una manga no pescada puede descartarse (faltar es la peor) o si solo se
 * descartan mangas pescadas. Vale para los dos sistemas, y el cuadro manga a
 * manga tacha la que de verdad se descarta.
 */
class DescartesTest extends TestCase
{
    use RefreshDatabase;

    private Seccion $orilla;

    private Temporada $temporada;

    private Manga $primera;

    private Manga $segunda;

    private Socio $alberto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        $this->orilla = Seccion::where('nombre', 'Orilla')->firstOrFail();
        $this->temporada = Temporada::where('activa', true)->firstOrFail();
        [$this->primera, $this->segunda] = Manga::where('seccion_id', $this->orilla->id)->where('estado', Manga::ESTADO_CELEBRADA)->orderBy('fecha')->get();

        // Alberto Rey se pierde la primera manga; en la segunda pesca 2.650 g.
        $this->alberto = Socio::where('nombre', 'Alberto Rey')->firstOrFail();
        $this->primera->participacions()->where('socio_id', $this->alberto->id)->delete();
    }

    private function fila(string $nombre): object
    {
        return Scoring::rankingTemporada($this->temporada)->firstWhere('nombre', 'Orilla')
            ->filas->first(fn (object $f) => $f->socio->nombre === $nombre);
    }

    public function test_suma_lo_pescado_solo_entre_las_pescadas_quita_la_peor_de_las_que_fue(): void
    {
        $this->orilla->update(['descartes' => 1, 'descartes_ausencias' => false]);

        // Alberto solo fue a una: se le descarta esa y se queda a cero (doble castigo, pero es lo que pide el club).
        $this->assertSame(0, $this->fila('Alberto Rey')->puntos);
        $this->assertStringContainsString('No cuenta la peor manga de cada socio.', $this->orilla->fresh()->resumenReglas());

        $cuadro = Scoring::cuadroSeccion($this->temporada, $this->orilla->fresh());
        $alberto = $cuadro->filas->first(fn (object $f) => $f->socio->nombre === 'Alberto Rey');
        $this->assertSame([$this->segunda->id], $alberto->descartadas);
        $this->assertTrue($alberto->celdas[$this->segunda->id]->descartada);
    }

    public function test_suma_lo_pescado_tambien_las_no_pescadas_descarta_la_manga_a_la_que_falto(): void
    {
        $this->orilla->update(['descartes' => 1, 'descartes_ausencias' => true]);

        // Faltar es la peor manga: se descarta la 1ª y la 2ª cuenta entera.
        $this->assertSame(2650, $this->fila('Alberto Rey')->puntos);
        // Mario fue a las dos: se le descarta su peor pescada, como siempre.
        $this->assertSame(4350, $this->fila('Mario López')->puntos);
        $this->assertStringContainsString('No cuenta la peor manga de cada socio, y no ir a una manga cuenta como la peor.', $this->orilla->fresh()->resumenReglas());

        $cuadro = Scoring::cuadroSeccion($this->temporada, $this->orilla->fresh());
        $alberto = $cuadro->filas->first(fn (object $f) => $f->socio->nombre === 'Alberto Rey');
        $this->assertSame([$this->primera->id], $alberto->descartadas);
        $this->assertNull($alberto->celdas[$this->primera->id]);
        $this->assertFalse($alberto->celdas[$this->segunda->id]->descartada);

        // Y con puntos por asistencia, la manga descartada no los cobra: 2650 + 500 (solo la 2ª).
        $this->orilla->update(['puntos_participacion' => 500]);
        $this->assertSame(3150, $this->fila('Alberto Rey')->puntos);
    }

    public function test_por_puestos_las_dos_opciones(): void
    {
        $this->orilla->update(['sistema_puntuacion' => Seccion::SISTEMA_PUESTOS, 'puntos_no_asistencia' => 48, 'descartes' => 1]);

        // También las no pescadas (lo de la federación): el 48 es la peor manga y se descarta.
        $this->orilla->update(['descartes_ausencias' => true]);
        $conAusencia = $this->fila('Alberto Rey')->puntos;
        $puestoSegunda = Scoring::cuadroSeccion($this->temporada, $this->orilla->fresh())
            ->filas->first(fn (object $f) => $f->socio->nombre === 'Alberto Rey')->celdas[$this->segunda->id]->puntos;
        $this->assertEquals($puestoSegunda, $conAusencia);

        // Solo las pescadas: el 48 cuenta y se le descarta su única manga pescada.
        $this->orilla->update(['descartes_ausencias' => false]);
        $this->assertEquals(48, $this->fila('Alberto Rey')->puntos);
        $cuadro = Scoring::cuadroSeccion($this->temporada, $this->orilla->fresh());
        $alberto = $cuadro->filas->first(fn (object $f) => $f->socio->nombre === 'Alberto Rey');
        $this->assertSame([$this->segunda->id], $alberto->descartadas);
    }

    public function test_el_cuadro_tacha_la_ausencia_descartada(): void
    {
        $this->orilla->update(['sistema_puntuacion' => Seccion::SISTEMA_PUESTOS, 'puntos_no_asistencia' => 48, 'descartes' => 1, 'descartes_ausencias' => true]);

        $this->actingAs(User::where('email', 'admin@plica.test')->firstOrFail());
        $html = $this->get("/admin/ranking/{$this->orilla->id}")->assertOk()->getContent();
        $this->assertStringContainsString('No participó: 48 pts · manga descartada', $html);
        $this->assertStringContainsString('<s>48</s>', $html);

        $this->get('/c/cd-pesca-piloto/orilla')->assertOk()->assertSee('No participó: 48 pts · manga descartada');
    }

    public function test_se_configura_en_el_formulario_solo_cuando_hay_descartes(): void
    {
        $this->actingAs(User::where('email', 'admin@plica.test')->firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(EditSeccion::class, ['record' => $this->orilla->getRouteKey()])
            ->fillForm(['descartes' => 1, 'descartes_ausencias' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($this->orilla->fresh()->descartes_ausencias);
        $this->assertSame(1, $this->orilla->fresh()->descartes);
    }
}
