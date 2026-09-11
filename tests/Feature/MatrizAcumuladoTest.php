<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Services\Scoring;
use Database\Seeders\ClubPruebaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cierre del sistema «suma lo pescado»: la matriz del Club de Pruebas (18
 * secciones: peso, medida y piezas × seis combinaciones de asistencia,
 * empates y descartes) contra resultados calculados aparte del motor, a mano
 * y con una implementación independiente (scratchpad/oraculo_acumulado.py,
 * 11 de septiembre de 2026). Cada lista: [socio, puesto, puntos, mangas].
 */
class MatrizAcumuladoTest extends TestCase
{
    use RefreshDatabase;

    private const ESPERADO = [
        'matriz-peso-1' => [
            ['Paco Jiménez', 1, 5500, 3],
            ['Iván Perea', 2, 5000, 2],
            ['Mario López', 3, 5000, 2],
            ['Andrés Molina', 4, 3200, 2],
            ['Sergio del Río', 5, 2200, 3],
            ['Rubén Castaño', 6, 2000, 2],
        ],
        'matriz-peso-2' => [
            ['Paco Jiménez', 1, 7000, 3],
            ['Mario López', 2, 6000, 2],
            ['Iván Perea', 3, 6000, 2],
            ['Andrés Molina', 4, 4200, 2],
            ['Sergio del Río', 5, 3700, 3],
            ['Rubén Castaño', 6, 3000, 2],
        ],
        'matriz-peso-3' => [
            ['Paco Jiménez', 1, 5500, 3],
            ['Mario López', 2, 5000, 2],
            ['Iván Perea', 2, 5000, 2],
            ['Andrés Molina', 4, 3200, 2],
            ['Sergio del Río', 5, 2200, 3],
            ['Rubén Castaño', 6, 2000, 2],
        ],
        'matriz-peso-4' => [
            ['Paco Jiménez', 1, 6500, 3],
            ['Iván Perea', 2, 4500, 2],
            ['Mario López', 3, 3500, 2],
            ['Sergio del Río', 4, 3200, 3],
            ['Andrés Molina', 5, 2700, 2],
            ['Rubén Castaño', 6, 2000, 2],
        ],
        'matriz-peso-5' => [
            ['Paco Jiménez', 1, 5500, 3],
            ['Mario López', 2, 5000, 2],
            ['Iván Perea', 3, 5000, 2],
            ['Andrés Molina', 4, 3200, 2],
            ['Sergio del Río', 5, 2200, 3],
            ['Rubén Castaño', 6, 2000, 2],
        ],
        'matriz-peso-6' => [
            ['Paco Jiménez', 1, 6500, 3],
            ['Mario López', 2, 6000, 2],
            ['Iván Perea', 2, 6000, 2],
            ['Andrés Molina', 4, 4200, 2],
            ['Sergio del Río', 5, 3200, 3],
            ['Rubén Castaño', 6, 3000, 2],
        ],
        'matriz-piezas-1' => [
            ['Iván Perea', 1, 6, 2],
            ['Paco Jiménez', 2, 5, 3],
            ['Mario López', 3, 5, 2],
            ['Andrés Molina', 4, 4, 2],
            ['Rubén Castaño', 5, 4, 2],
            ['Sergio del Río', 6, 3, 3],
        ],
        'matriz-piezas-2' => [
            ['Paco Jiménez', 1, 1505, 3],
            ['Sergio del Río', 2, 1503, 3],
            ['Iván Perea', 3, 1006, 2],
            ['Mario López', 4, 1005, 2],
            ['Andrés Molina', 5, 1004, 2],
            ['Rubén Castaño', 6, 1004, 2],
        ],
        'matriz-piezas-3' => [
            ['Iván Perea', 1, 6, 2],
            ['Mario López', 2, 5, 2],
            ['Paco Jiménez', 2, 5, 3],
            ['Andrés Molina', 4, 4, 2],
            ['Rubén Castaño', 4, 4, 2],
            ['Sergio del Río', 6, 3, 3],
        ],
        'matriz-piezas-4' => [
            ['Paco Jiménez', 1, 1004, 3],
            ['Sergio del Río', 2, 1003, 3],
            ['Iván Perea', 3, 504, 2],
            ['Mario López', 4, 503, 2],
            ['Andrés Molina', 5, 503, 2],
            ['Rubén Castaño', 6, 502, 2],
        ],
        'matriz-piezas-5' => [
            ['Iván Perea', 1, 6, 2],
            ['Mario López', 2, 5, 2],
            ['Paco Jiménez', 3, 4, 3],
            ['Andrés Molina', 4, 4, 2],
            ['Rubén Castaño', 5, 4, 2],
            ['Sergio del Río', 6, 3, 3],
        ],
        'matriz-piezas-6' => [
            ['Iván Perea', 1, 1006, 2],
            ['Mario López', 2, 1005, 2],
            ['Paco Jiménez', 3, 1004, 3],
            ['Andrés Molina', 3, 1004, 2],
            ['Rubén Castaño', 3, 1004, 2],
            ['Sergio del Río', 6, 1003, 3],
        ],
        'matriz-medida-1' => [
            ['Paco Jiménez', 1, 2500, 3],
            ['Iván Perea', 2, 2000, 2],
            ['Mario López', 3, 2000, 2],
            ['Andrés Molina', 4, 1800, 2],
            ['Rubén Castaño', 5, 1350, 2],
            ['Sergio del Río', 6, 1250, 3],
        ],
        'matriz-medida-2' => [
            ['Paco Jiménez', 1, 4000, 3],
            ['Mario López', 2, 3000, 2],
            ['Iván Perea', 3, 3000, 2],
            ['Andrés Molina', 4, 2800, 2],
            ['Sergio del Río', 5, 2750, 3],
            ['Rubén Castaño', 6, 2350, 2],
        ],
        'matriz-medida-3' => [
            ['Paco Jiménez', 1, 2500, 3],
            ['Mario López', 2, 2000, 2],
            ['Iván Perea', 2, 2000, 2],
            ['Andrés Molina', 4, 1800, 2],
            ['Rubén Castaño', 5, 1350, 2],
            ['Sergio del Río', 6, 1250, 3],
        ],
        'matriz-medida-4' => [
            ['Paco Jiménez', 1, 3500, 3],
            ['Sergio del Río', 2, 2250, 3],
            ['Iván Perea', 3, 2000, 2],
            ['Andrés Molina', 4, 1800, 2],
            ['Mario López', 5, 1700, 2],
            ['Rubén Castaño', 6, 1400, 2],
        ],
        'matriz-medida-5' => [
            ['Paco Jiménez', 1, 2500, 3],
            ['Mario López', 2, 2000, 2],
            ['Iván Perea', 3, 2000, 2],
            ['Andrés Molina', 4, 1800, 2],
            ['Rubén Castaño', 5, 1350, 2],
            ['Sergio del Río', 6, 1250, 3],
        ],
        'matriz-medida-6' => [
            ['Paco Jiménez', 1, 3500, 3],
            ['Mario López', 2, 3000, 2],
            ['Iván Perea', 2, 3000, 2],
            ['Andrés Molina', 4, 2800, 2],
            ['Rubén Castaño', 5, 2350, 2],
            ['Sergio del Río', 6, 2250, 3],
        ],
    ];

    public function test_las_18_secciones_de_la_matriz_dan_lo_calculado_aparte(): void
    {
        $this->seed(ClubPruebaSeeder::class);
        $club = Club::where('slug', 'club-de-pruebas')->firstOrFail();
        $temporada = $club->temporadaActiva();
        $ranking = Scoring::rankingTemporada($temporada);

        foreach (self::ESPERADO as $slug => $esperado) {
            $seccion = $club->seccions()->where('slug', $slug)->firstOrFail();
            $grupo = $ranking->firstWhere('seccionId', $seccion->id);
            $this->assertNotNull($grupo, $slug);

            $real = $grupo->filas->map(fn (object $f) => [$f->socio->nombre, $f->puesto, $f->puntos, $f->mangas])->all();
            // Los empatados exactos pueden salir en cualquier orden entre sí: se compara como conjunto.
            sort($real);
            $ordenado = $esperado;
            sort($ordenado);
            $this->assertSame($ordenado, $real, $seccion->nombre);

            // El cuadro manga a manga da los mismos puntos y puestos que el ranking.
            $cuadro = Scoring::cuadroSeccion($temporada, $seccion);
            $this->assertSame(
                $grupo->filas->map(fn (object $f) => [$f->socio->id, $f->puesto, $f->puntos])->all(),
                $cuadro->filas->map(fn (object $f) => [$f->socio->id, $f->puesto, $f->puntos])->all(),
                $seccion->nombre,
            );
        }
    }

    public function test_cada_variable_esta_cubierta(): void
    {
        $this->seed(ClubPruebaSeeder::class);
        $secciones = Club::where('slug', 'club-de-pruebas')->firstOrFail()->seccions()->where('slug', 'like', 'matriz-%')->get();

        $this->assertCount(18, $secciones);
        $this->assertSame(['medida', 'peso', 'piezas'], $secciones->pluck('criterio')->unique()->sort()->values()->all());
        $this->assertSame([0, 500], $secciones->pluck('puntos_participacion')->unique()->sort()->values()->all());
        $this->assertSame(['compartido', 'peso', 'pieza_mayor', 'piezas'], $secciones->pluck('desempate')->unique()->sort()->values()->all());
        $this->assertSame([0, 1], $secciones->pluck('descartes')->unique()->sort()->values()->all());
        $this->assertSame([false, true], $secciones->pluck('descartes_ausencias')->unique()->sort()->values()->all());
    }
}
