<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Services\Scoring;
use Database\Seeders\ClubPruebaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cierre del sistema de la federación (suma los puestos): la matriz del Club
 * de Pruebas (30 secciones: peso, medida y piezas × diez combinaciones de
 * desempate, ausencia, bolo y descartes) contra resultados calculados aparte
 * del motor, a mano y con una implementación independiente
 * (scratchpad/oraculo_federacion.py, 11 de septiembre de 2026). Cada lista:
 * [socio, puesto, puntos, mangas]. Los puntos pueden llevar decimales.
 * Desde el 14 de septiembre de 2026, en cada sección hay un séptimo socio (Toni
 * Salgado) que es de ella pero no ha ido a ninguna manga: suma la ausencia de
 * cada manga (7 fijos o último + 1) menos lo que descarte la sección.
 */
class MatrizFederacionTest extends TestCase
{
    use RefreshDatabase;

    private const ESPERADO = [
        'federacion-peso-1' => [
            ['Paco Jiménez', 1, 6.5, 3],
            ['Mario López', 2, 9.5, 2],
            ['Andrés Molina', 3, 10, 2],
            ['Iván Perea', 3, 10, 2],
            ['Sergio del Río', 5, 12, 3],
            ['Rubén Castaño', 6, 14, 2],
            ['Toni Salgado', 7, 17, 0],
        ],
        'federacion-peso-2' => [
            ['Paco Jiménez', 1, 3.5, 3],
            ['Andrés Molina', 2, 4, 2],
            ['Iván Perea', 2, 4, 2],
            ['Mario López', 4, 4.5, 2],
            ['Sergio del Río', 5, 7.5, 3],
            ['Rubén Castaño', 6, 9, 2],
            ['Toni Salgado', 7, 14, 0],
        ],
        'federacion-peso-3' => [
            ['Paco Jiménez', 1, 6, 3],
            ['Iván Perea', 2, 10, 2],
            ['Sergio del Río', 3, 10, 3],
            ['Mario López', 4, 11, 2],
            ['Andrés Molina', 5, 11, 2],
            ['Rubén Castaño', 6, 16, 2],
            ['Toni Salgado', 7, 21, 0],
        ],
        'federacion-peso-4' => [
            ['Paco Jiménez', 1, 3, 3],
            ['Mario López', 2, 7, 2],
            ['Andrés Molina', 2, 7, 2],
            ['Iván Perea', 4, 7, 2],
            ['Rubén Castaño', 5, 9, 2],
            ['Sergio del Río', 6, 9, 3],
            ['Toni Salgado', 7, 17, 0],
        ],
        'federacion-peso-5' => [
            ['Mario López', 1, 11, 2],
            ['Andrés Molina', 1, 11, 2],
            ['Toni Salgado', 3, 21, 0],
            ['Paco Jiménez', 4, 34, 3],
            ['Iván Perea', 5, 38, 2],
            ['Rubén Castaño', 6, 42, 2],
            ['Sergio del Río', 7, 64, 3],
        ],
        'federacion-peso-6' => [
            ['Paco Jiménez', 1, 3, 3],
            ['Mario López', 2, 4, 2],
            ['Andrés Molina', 2, 4, 2],
            ['Iván Perea', 4, 6, 2],
            ['Sergio del Río', 5, 9, 3],
            ['Rubén Castaño', 5, 9, 2],
            ['Toni Salgado', 7, 11, 0],
        ],
        'federacion-peso-7' => [
            ['Iván Perea', 1, 4, 2],
            ['Mario López', 2, 4, 2],
            ['Paco Jiménez', 3, 4, 3],
            ['Andrés Molina', 4, 4, 2],
            ['Sergio del Río', 5, 7, 3],
            ['Rubén Castaño', 6, 9.5, 2],
            ['Toni Salgado', 7, 11, 0],
        ],
        'federacion-peso-8' => [
            ['Paco Jiménez', 1, 3.5, 3],
            ['Andrés Molina', 2, 8, 2],
            ['Iván Perea', 2, 8, 2],
            ['Mario López', 4, 8.5, 2],
            ['Rubén Castaño', 5, 11.5, 2],
            ['Toni Salgado', 6, 21, 0],
            ['Sergio del Río', 7, 34.5, 3],
        ],
        'federacion-peso-9' => [
            ['Paco Jiménez', 1, 3, 3],
            ['Iván Perea', 2, 3, 2],
            ['Andrés Molina', 3, 4, 2],
            ['Mario López', 4, 5, 2],
            ['Sergio del Río', 5, 6, 3],
            ['Rubén Castaño', 6, 8, 2],
            ['Toni Salgado', 7, 14, 0],
        ],
        'federacion-peso-10' => [
            ['Paco Jiménez', 1, 7, 3],
            ['Mario López', 2, 11, 2],
            ['Andrés Molina', 2, 11, 2],
            ['Iván Perea', 4, 12, 2],
            ['Sergio del Río', 5, 13, 3],
            ['Rubén Castaño', 6, 16, 2],
            ['Toni Salgado', 7, 21, 0],
        ],
        'federacion-piezas-1' => [
            ['Mario López', 1, 9.5, 2],
            ['Paco Jiménez', 1, 9.5, 3],
            ['Andrés Molina', 3, 10, 2],
            ['Iván Perea', 3, 10, 2],
            ['Sergio del Río', 5, 10.5, 3],
            ['Rubén Castaño', 6, 12.5, 2],
            ['Toni Salgado', 7, 17, 0],
        ],
        'federacion-piezas-2' => [
            ['Andrés Molina', 1, 4, 2],
            ['Iván Perea', 1, 4, 2],
            ['Mario López', 3, 4.5, 2],
            ['Paco Jiménez', 3, 4.5, 3],
            ['Sergio del Río', 5, 6, 3],
            ['Rubén Castaño', 6, 7.5, 2],
            ['Toni Salgado', 7, 14, 0],
        ],
        'federacion-piezas-3' => [
            ['Paco Jiménez', 1, 8, 3],
            ['Iván Perea', 2, 10, 2],
            ['Sergio del Río', 3, 10, 3],
            ['Mario López', 4, 11, 2],
            ['Andrés Molina', 5, 11, 2],
            ['Rubén Castaño', 6, 14, 2],
            ['Toni Salgado', 7, 21, 0],
        ],
        'federacion-piezas-4' => [
            ['Mario López', 1, 6, 2],
            ['Paco Jiménez', 2, 6, 3],
            ['Andrés Molina', 3, 7, 2],
            ['Iván Perea', 4, 7, 2],
            ['Sergio del Río', 5, 7, 3],
            ['Rubén Castaño', 6, 8, 2],
            ['Toni Salgado', 7, 17, 0],
        ],
        'federacion-piezas-5' => [
            ['Mario López', 1, 10, 2],
            ['Andrés Molina', 2, 11, 2],
            ['Toni Salgado', 3, 21, 0],
            ['Paco Jiménez', 4, 37, 3],
            ['Iván Perea', 5, 38, 2],
            ['Rubén Castaño', 6, 40, 2],
            ['Sergio del Río', 7, 63, 3],
        ],
        'federacion-piezas-6' => [
            ['Mario López', 1, 3, 2],
            ['Andrés Molina', 2, 4, 2],
            ['Paco Jiménez', 3, 6, 3],
            ['Iván Perea', 3, 6, 2],
            ['Sergio del Río', 5, 7, 3],
            ['Rubén Castaño', 5, 7, 2],
            ['Toni Salgado', 7, 11, 0],
        ],
        'federacion-piezas-7' => [
            ['Paco Jiménez', 1, 4, 3],
            ['Mario López', 2, 4, 2],
            ['Andrés Molina', 3, 4, 2],
            ['Iván Perea', 3, 4, 2],
            ['Sergio del Río', 5, 7, 3],
            ['Rubén Castaño', 6, 7.5, 2],
            ['Toni Salgado', 7, 11, 0],
        ],
        'federacion-piezas-8' => [
            ['Paco Jiménez', 1, 6.5, 3],
            ['Andrés Molina', 2, 8, 2],
            ['Iván Perea', 2, 8, 2],
            ['Mario López', 4, 8.5, 2],
            ['Rubén Castaño', 5, 10, 2],
            ['Toni Salgado', 6, 21, 0],
            ['Sergio del Río', 7, 33, 3],
        ],
        'federacion-piezas-9' => [
            ['Mario López', 1, 3, 2],
            ['Iván Perea', 2, 3, 2],
            ['Paco Jiménez', 3, 4, 3],
            ['Andrés Molina', 4, 4, 2],
            ['Sergio del Río', 5, 5, 3],
            ['Rubén Castaño', 6, 7, 2],
            ['Toni Salgado', 7, 14, 0],
        ],
        'federacion-piezas-10' => [
            ['Mario López', 1, 10, 2],
            ['Paco Jiménez', 1, 10, 3],
            ['Andrés Molina', 3, 11, 2],
            ['Sergio del Río', 3, 11, 3],
            ['Iván Perea', 5, 12, 2],
            ['Rubén Castaño', 6, 14, 2],
            ['Toni Salgado', 7, 21, 0],
        ],
        'federacion-medida-1' => [
            ['Paco Jiménez', 1, 6.5, 3],
            ['Andrés Molina', 2, 10, 2],
            ['Iván Perea', 2, 10, 2],
            ['Sergio del Río', 4, 11, 3],
            ['Mario López', 5, 11.5, 2],
            ['Rubén Castaño', 6, 13, 2],
            ['Toni Salgado', 7, 17, 0],
        ],
        'federacion-medida-2' => [
            ['Paco Jiménez', 1, 3.5, 3],
            ['Andrés Molina', 2, 4, 2],
            ['Iván Perea', 2, 4, 2],
            ['Mario López', 4, 6.5, 2],
            ['Sergio del Río', 4, 6.5, 3],
            ['Rubén Castaño', 6, 8, 2],
            ['Toni Salgado', 7, 14, 0],
        ],
        'federacion-medida-3' => [
            ['Paco Jiménez', 1, 5, 3],
            ['Sergio del Río', 2, 9, 3],
            ['Iván Perea', 3, 10, 2],
            ['Andrés Molina', 4, 11, 2],
            ['Mario López', 5, 14, 2],
            ['Rubén Castaño', 6, 15, 2],
            ['Toni Salgado', 7, 21, 0],
        ],
        'federacion-medida-4' => [
            ['Paco Jiménez', 1, 4, 3],
            ['Mario López', 2, 6, 2],
            ['Andrés Molina', 3, 7, 2],
            ['Iván Perea', 4, 7, 2],
            ['Rubén Castaño', 5, 8, 2],
            ['Sergio del Río', 6, 8, 3],
            ['Toni Salgado', 7, 17, 0],
        ],
        'federacion-medida-5' => [
            ['Andrés Molina', 1, 11, 2],
            ['Mario López', 2, 14, 2],
            ['Toni Salgado', 3, 21, 0],
            ['Paco Jiménez', 4, 33, 3],
            ['Iván Perea', 5, 38, 2],
            ['Rubén Castaño', 6, 41, 2],
            ['Sergio del Río', 7, 63, 3],
        ],
        'federacion-medida-6' => [
            ['Paco Jiménez', 1, 3, 3],
            ['Andrés Molina', 2, 4, 2],
            ['Mario López', 3, 6, 2],
            ['Iván Perea', 3, 6, 2],
            ['Sergio del Río', 5, 8, 3],
            ['Rubén Castaño', 5, 8, 2],
            ['Toni Salgado', 7, 11, 0],
        ],
        'federacion-medida-7' => [
            ['Paco Jiménez', 1, 3, 3],
            ['Iván Perea', 2, 4, 2],
            ['Andrés Molina', 3, 4, 2],
            ['Sergio del Río', 4, 6, 3],
            ['Mario López', 5, 7, 2],
            ['Rubén Castaño', 6, 8.5, 2],
            ['Toni Salgado', 7, 11, 0],
        ],
        'federacion-medida-8' => [
            ['Paco Jiménez', 1, 3.5, 3],
            ['Andrés Molina', 2, 8, 2],
            ['Iván Perea', 2, 8, 2],
            ['Mario López', 4, 8.5, 2],
            ['Rubén Castaño', 5, 10.5, 2],
            ['Toni Salgado', 6, 21, 0],
            ['Sergio del Río', 7, 33.5, 3],
        ],
        'federacion-medida-9' => [
            ['Iván Perea', 1, 3, 2],
            ['Paco Jiménez', 2, 4, 3],
            ['Andrés Molina', 3, 4, 2],
            ['Mario López', 4, 6, 2],
            ['Sergio del Río', 5, 6, 3],
            ['Rubén Castaño', 6, 7, 2],
            ['Toni Salgado', 7, 14, 0],
        ],
        'federacion-medida-10' => [
            ['Paco Jiménez', 1, 7, 3],
            ['Andrés Molina', 2, 11, 2],
            ['Sergio del Río', 3, 12, 3],
            ['Iván Perea', 3, 12, 2],
            ['Mario López', 5, 13, 2],
            ['Rubén Castaño', 6, 15, 2],
            ['Toni Salgado', 7, 21, 0],
        ],
    ];

    public function test_las_30_secciones_de_la_matriz_dan_lo_calculado_aparte(): void
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
            sort($real);
            $ordenado = $esperado;
            sort($ordenado);
            $this->assertEquals($ordenado, $real, $seccion->nombre);

            // El cuadro manga a manga da los mismos puntos y puestos que el ranking.
            $cuadro = Scoring::cuadroSeccion($temporada, $seccion);
            $this->assertEquals(
                $grupo->filas->map(fn (object $f) => [$f->socio->id, $f->puesto, $f->puntos])->all(),
                $cuadro->filas->map(fn (object $f) => [$f->socio->id, $f->puesto, $f->puntos])->all(),
                $seccion->nombre,
            );
        }
    }

    public function test_cada_variable_esta_cubierta(): void
    {
        $this->seed(ClubPruebaSeeder::class);
        $secciones = Club::where('slug', 'club-de-pruebas')->firstOrFail()->seccions()->where('slug', 'like', 'federacion-%')->get();

        $this->assertCount(30, $secciones);
        $this->assertSame(['medida', 'peso', 'piezas'], $secciones->pluck('criterio')->unique()->sort()->values()->all());
        $this->assertSame(['compartido', 'menos_piezas', 'peso', 'pieza_mayor', 'piezas', 'promedio'], $secciones->pluck('desempate')->unique()->sort()->values()->all());
        $this->assertSame([0, 7], $secciones->pluck('puntos_no_asistencia')->unique()->sort()->values()->all());
        $this->assertSame(['ausencia', 'fijo', 'media', 'primer_libre', 'ultimo'], $secciones->pluck('bolo')->unique()->sort()->values()->all());
        $this->assertSame([0, 1], $secciones->pluck('descartes')->unique()->sort()->values()->all());
        $this->assertSame([false, true], $secciones->pluck('descartes_ausencias')->unique()->sort()->values()->all());
    }

    /** La fórmula del bolo que pasó Bass Extremadura, tal cual: ((C + 1) + N) / 2. */
    public function test_la_formula_del_bolo(): void
    {
        $this->seed(ClubPruebaSeeder::class);
        $club = Club::where('slug', 'club-de-pruebas')->firstOrFail();
        $seccion = $club->seccions()->where('slug', 'federacion-peso-1')->firstOrFail();
        $primera = $seccion->mangas()->orderBy('fecha')->firstOrFail();

        // 1ª manga de peso: pescaron 3 (C) y fueron 5 (N): cada bolo (4 + 5) / 2 = 4,5.
        $puntos = Scoring::puntosPorPuesto(Scoring::clasificacionManga($primera)->first()->filas, 'promedio', 'media');
        $bolos = array_filter($puntos, fn ($p) => $p == 4.5);
        $this->assertCount(2, $bolos);

        // Y las otras cuatro opciones sobre la misma manga.
        $filas = Scoring::clasificacionManga($primera)->first()->filas;
        $this->assertCount(2, array_filter(Scoring::puntosPorPuesto($filas, 'promedio', 'primer_libre'), fn ($p) => $p === 4));
        $this->assertCount(2, array_filter(Scoring::puntosPorPuesto($filas, 'promedio', 'ultimo'), fn ($p) => $p === 5));
        $this->assertCount(2, array_filter(Scoring::puntosPorPuesto($filas, 'promedio', 'fijo', 30), fn ($p) => $p === 30));
        $this->assertCount(2, array_filter(Scoring::puntosPorPuesto($filas, 'promedio', 'ausencia', 0, 48), fn ($p) => $p === 48));
        $this->assertCount(2, array_filter(Scoring::puntosPorPuesto($filas, 'promedio', 'ausencia', 0, 0), fn ($p) => $p === 6)); // último + 1
    }
}
