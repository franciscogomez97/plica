<?php

namespace Tests\Feature;

use App\Filament\App\Pages\RankingSeccion;
use App\Models\Seccion;
use App\Models\Socio;
use App\Models\User;
use App\Services\Podio;
use App\Services\Scoring;
use App\Support\Participante;
use Database\Seeders\DemoSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenario;
use Tests\TestCase;

/**
 * La revisión con lupa de los rankings (15 de septiembre de 2026): que el
 * empate de la general se pueda entender, que ausente y bolo se distingan,
 * que los nombres se abrevien bien, que el socio vea la misma página que el
 * club, y que «1 pt» sea «1 pt».
 */
class RevisionRankingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_abreviatura_respeta_nombres_compuestos_y_particulas(): void
    {
        $this->assertSame('Mario L.', Participante::abreviar('Mario López García'));
        $this->assertSame('Miguel Ángel T.', Participante::abreviar('Miguel Ángel Toro'));
        $this->assertSame('José Luis M.', Participante::abreviar('José Luis Moreno'));
        $this->assertSame('Sergio del R.', Participante::abreviar('Sergio del Río'));
        $this->assertSame('Paco de la T.', Participante::abreviar('Paco de la Torre'));
        $this->assertSame('Fernando M.', Participante::abreviar('Fernando Montes'));
        $this->assertSame('Ana San M.', Participante::abreviar('Ana San Martín'));
        $this->assertSame('Fernando', Participante::abreviar('Fernando'));
        $this->assertSame('Miguel Á.', Participante::abreviar('Miguel Ángel'), 'sin apellido detrás, «Ángel» es el apellido');
        $this->assertSame(Participante::abreviar('Sergio del Río'), Podio::abreviar('Sergio del Río'), 'la tarjeta abrevia igual que la web');

        // Donde no cabe, una cadena de recortes antes de los puntos suspensivos.
        $this->assertSame(['Juan Antonio Pérez', 'Juan Antonio P.', 'J. Antonio P.', 'Juan A.', 'Juan'], Participante::abreviaturas('Juan Antonio Pérez'));
        $this->assertSame(['Sergio del Río', 'Sergio del R.', 'Sergio R.', 'Sergio'], Participante::abreviaturas('Sergio del Río'));
        $this->assertSame(['Paco de la Torre', 'Paco de la T.', 'Paco T.', 'Paco'], Participante::abreviaturas('Paco de la Torre'));
        $this->assertSame(['Fernando Montes', 'Fernando M.', 'Fernando'], Participante::abreviaturas('Fernando Montes'));
        $this->assertSame(['Fernando'], Participante::abreviaturas('Fernando'));
    }

    public function test_en_la_tarjeta_los_nombres_largos_se_recortan_por_la_cadena_y_no_con_puntos(): void
    {
        $spec = [];
        foreach (['Juan Antonio Pérez', 'Sergio del Río', 'José Luis Moreno', 'Paco de la Torre', 'Fernando Montes', 'Miguel Ángel Toro', 'Antonio Jiménez Rodríguez', 'Ana', 'Beatriz Sanz', 'Carlos Molina', 'Diego Ruiz', 'Elena Vidal'] as $i => $n) {
            $spec[$n] = [12450 - $i * 500];
        }
        $escenario = Escenario::crear($spec, [], 'lupa-nombres');
        $ruta = Podio::deManga($escenario->mangas->first());
        $this->assertFileExists($ruta);
        if ($destino = getenv('PODIO_GUARDAR')) {
            copy($ruta, $destino.'/podio-nombres.jpg');
        }
    }

    public function test_un_punto_es_un_pt_y_con_una_sola_pieza_no_se_repite_la_pieza_mayor(): void
    {
        $this->assertSame('1 pt', Scoring::pts(1));
        $this->assertSame('18,5 pts', Scoring::pts(18.5));
        $this->assertSame('2 pts', Scoring::pts(2));

        $unaPieza = (object) ['piezas' => 1, 'peso' => 0, 'medida' => 930, 'mayorGramos' => 0, 'mayorMm' => 930];
        $this->assertSame('1 pieza', Scoring::valorSecundario(Seccion::CRITERIO_MEDIDA, $unaPieza));
        $dosPiezas = (object) ['piezas' => 2, 'peso' => 0, 'medida' => 1500, 'mayorGramos' => 0, 'mayorMm' => 930];
        $this->assertSame('2 piezas · mayor 93 cm', Scoring::valorSecundario(Seccion::CRITERIO_MEDIDA, $dosPiezas));
    }

    public function test_por_puestos_el_cuadro_ensena_lo_que_decide_el_empate_de_la_general_y_los_ausentes_dicen_no_fue(): void
    {
        // Ana y Bea empatan a puntos; la general se decide por centímetros y hay que verlos.
        $escenario = Escenario::crear([
            'Ana' => [500, 300],
            'Bea' => [300, 500],
            'Cai' => [100, '—'],
        ], ['criterio' => Seccion::CRITERIO_MEDIDA, 'sistema_puntuacion' => Seccion::SISTEMA_PUESTOS, 'desempate_general' => Seccion::DESEMPATE_GENERAL_MEDIDA], 'lupa-puestos');

        $html = $this->get('/c/'.$escenario->club->slug.'/'.$escenario->seccion->slug)->assertOk()->getContent();
        $this->assertStringContainsString('<th class="desempate" title="Decide los empates de la clasificación general">cm<small>empate</small></th>', $html);
        $this->assertStringContainsString('<td class="desempate">80 cm</td>', $html);
        $this->assertStringNotContainsString('<th class="piezas">Piezas</th>', $html, 'con la columna de empate, la de piezas sobra (no cabía en escritorio)');
        $this->assertStringContainsString('<span class="v">no fue</span><span class="m">3 pts</span>', $html, 'el ausente dice «no fue» y lo que le cuesta');
        $this->assertStringContainsString('<span class="u"> pt</span>', $html, 'el ganador de la manga: 1 pt');
        $this->assertStringNotContainsString('1 pts', $html);
    }

    public function test_en_kilos_el_bolo_dice_0_kg_y_el_ausente_no_fue(): void
    {
        $escenario = Escenario::crear([
            'Ana' => [3000, 'bolo'],
            'Bea' => [2000, '—'],
        ], [], 'lupa-kilos');
        $html = $this->get('/c/'.$escenario->club->slug.'/'.$escenario->seccion->slug)->assertOk()->getContent();

        $this->assertStringContainsString('<span class="v font-semibold">0<span class="u"> kg</span></span>', $html, 'bolo: 0 kg');
        $this->assertStringContainsString('<span class="v">no fue</span>', $html, 'ausente: no fue');
        $this->assertStringNotContainsString('class="desempate"', $html, 'sumando lo pescado no hay columna de empate');
        $this->assertStringContainsString('pc-scroll', $html, 'pista de que el cuadro se desliza en el móvil');
    }

    public function test_el_socio_ve_la_misma_pagina_que_el_club_con_su_fila_resaltada(): void
    {
        $this->seed(DemoSeeder::class);
        $socio = User::where('email', 'socio@plica.test')->firstOrFail();
        $this->actingAs($socio);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        $orilla = Seccion::where('nombre', 'Orilla')->firstOrFail();
        $nombre = $socio->socio->nombre;

        $html = $this->get(RankingSeccion::getUrl(['seccion' => $orilla->id]))->assertOk()->getContent();
        // Mismo parcial que la web: cabecera, reglas plegadas, cuadro público, última manga.
        foreach (['Ranking Orilla', 'Cómo puntúa esta sección', 'Clasificación general', 'class="pc"', 'Última manga', 'publico'] as $t) {
            $this->assertStringContainsString($t, $html, $t);
        }
        // Su fila, resaltada, en el cuadro y en la lista de la última manga.
        $this->assertStringContainsString('<tr class="yo">', $html);
        $this->assertStringContainsString($nombre.' · tú', $html);
        $this->assertStringNotContainsString('.cuadro thead th', $html, 'ya no hay un segundo cuadro del panel');
        // Sin el encabezado de Filament: el título sale una vez, el del parcial.
        $this->assertStringNotContainsString('fi-header-heading', $html);
        $this->assertSame(1, substr_count($html, '>Ranking Orilla</h1>'), 'el título una sola vez');
    }

    public function test_las_columnas_de_la_tarjeta_se_reparten_mitad_y_mitad(): void
    {
        $spec = [];
        for ($i = 1; $i <= 21; $i++) {
            $spec["Pescador {$i}"] = [3000 - $i * 100];
        }
        $escenario = Escenario::crear($spec, [], 'lupa-columnas');
        $ruta = Podio::deManga($escenario->mangas->first());
        $this->assertFileExists($ruta); // 18 filas tras el podio: 9 y 9 (antes 15 y 3)
        if ($destino = getenv('PODIO_GUARDAR')) {
            copy($ruta, $destino.'/podio-columnas.jpg');
        }
    }
}
