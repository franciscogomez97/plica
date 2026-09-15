<?php

namespace Tests\Support;

/**
 * Un segundo motor de puntuación, escrito aparte del real y a lo simple, a
 * partir de las reglas tal como las cuenta la frase de cada sección. Sin
 * modelos, sin base de datos, sin optimizar: recibe el escenario y la
 * configuración y devuelve el ranking. Los tests comparan el motor real con
 * este; si discrepan, uno de los dos lee mal la regla.
 *
 * Celdas del escenario: ver Tests\Support\Escenario. Unidad de valor: gramos,
 * mm o piezas según el criterio.
 */
final class MotorDeReferencia
{
    /**
     * @param  array<string, array<int, mixed>>  $spec
     * @param  array<string, mixed>  $cfg  criterio, sistema, puntos_participacion, puntos_no_asistencia,
     *                                    descartes, descartes_ausencias, desempate, desempate_general, bolo, puntos_bolo
     * @return array{ranking: array<int, array{nombre: string, puesto: int, puntos: int|float, descartadas: int[]}>, mangas: array<int, array<string, int>>}
     *         el ranking ordenado y, por manga, el puesto de cada uno de los que fueron
     */
    public static function ranking(array $spec, array $cfg): array
    {
        $cfg += [
            'criterio' => 'peso', 'sistema' => 'acumulado', 'puntos_participacion' => 0, 'puntos_no_asistencia' => 0,
            'descartes' => 0, 'descartes_ausencias' => false, 'desempate' => null, 'desempate_general' => 'compartido',
            'bolo' => 'media', 'puntos_bolo' => 0,
        ];
        $cfg['desempate'] ??= $cfg['criterio'] === 'piezas' ? 'peso' : 'piezas';
        $numMangas = max(array_map('count', $spec) ?: [0]);

        // Lo que hizo cada socio en cada manga, en números.
        $hechos = [];
        foreach ($spec as $nombre => $celdas) {
            for ($m = 0; $m < $numMangas; $m++) {
                $hechos[$nombre][$m] = self::hecho($celdas[$m] ?? null, $cfg['criterio']);
            }
        }

        // Clasificación de cada manga (solo quienes fueron), con puesto compartido.
        $clasif = [];
        for ($m = 0; $m < $numMangas; $m++) {
            $filas = [];
            foreach ($hechos as $nombre => $porManga) {
                if ($porManga[$m] !== null) {
                    $filas[$nombre] = $porManga[$m];
                }
            }
            $clasif[$m] = self::numerar($filas, fn (array $h) => [$h['valor'], self::desempate($h, $cfg['criterio'], $cfg['desempate'])]);
        }

        $filas = [];
        foreach ($hechos as $nombre => $porManga) {
            $aporte = []; // manga => puntos que aporta al ranking (más es mejor en acumulado; menos es mejor por puestos)
            $pescada = [];
            foreach ($porManga as $m => $h) {
                $pescada[$m] = $h !== null;
                if ($cfg['sistema'] === 'puestos') {
                    $aporte[$m] = $h === null ? self::costeAusencia($clasif[$m], $cfg) : self::puntosEnManga($nombre, $clasif[$m], $cfg);
                } else {
                    $aporte[$m] = $h === null ? $cfg['puntos_no_asistencia'] : $h['valor'] + $cfg['puntos_participacion'];
                }
            }

            $descartadas = self::descartadas($aporte, $pescada, $cfg);
            $cuentan = array_diff_key($aporte, array_flip($descartadas));
            $total = array_sum($cuentan);
            $total = $total == (int) $total ? (int) $total : (float) $total;

            $suma = ['peso' => 0, 'medida' => 0, 'piezas' => 0, 'mayorGramos' => 0, 'mayorMm' => 0];
            $mejorManga = null;
            foreach ($porManga as $m => $h) {
                if ($h === null) {
                    continue;
                }
                $suma['peso'] += $h['peso'];
                $suma['medida'] += $h['medida'];
                $suma['piezas'] += $h['piezas'];
                $suma['mayorGramos'] = max($suma['mayorGramos'], $h['mayorGramos']);
                $suma['mayorMm'] = max($suma['mayorMm'], $h['mayorMm']);
                if ($cfg['sistema'] === 'puestos') {
                    $mejorManga = $mejorManga === null ? $aporte[$m] : min($mejorManga, $aporte[$m]);
                }
            }

            $filas[$nombre] = $suma + ['puntos' => $total, 'descartadas' => $descartadas, 'mejorManga' => $mejorManga,
                'valor' => $suma[$cfg['criterio'] === 'medida' ? 'medida' : ($cfg['criterio'] === 'piezas' ? 'piezas' : 'peso')]];
        }

        $clave = $cfg['sistema'] === 'puestos'
            ? fn (array $f) => [-$f['puntos'], self::desempateGeneral($f, $cfg)]
            : fn (array $f) => [$f['puntos'], self::desempate($f, $cfg['criterio'], $cfg['desempate'])];

        $resultado = [];
        foreach (self::numerar($filas, $clave) as $nombre => $f) {
            $resultado[] = ['nombre' => $nombre, 'puesto' => $f['puesto'], 'puntos' => $f['puntos'], 'descartadas' => $f['descartadas']];
        }

        return [
            'ranking' => $resultado,
            'mangas' => array_map(fn (array $c) => array_map(fn (array $f) => $f['puesto'], $c), $clasif),
        ];
    }

    /** Una celda en números: null si no fue. */
    private static function hecho(mixed $celda, string $criterio): ?array
    {
        if ($celda === null || $celda === '—') {
            return null;
        }
        $h = ['peso' => 0, 'medida' => 0, 'piezas' => 0, 'mayorGramos' => 0, 'mayorMm' => 0];
        if ($celda === 'bolo') {
            // fue y no pescó
        } elseif (is_int($celda)) {
            match ($criterio) {
                'medida' => [$h['medida'] = $celda, $h['piezas'] = 1, $h['mayorMm'] = $celda],
                'piezas' => [$h['piezas'] = $celda],
                default => [$h['peso'] = $celda, $h['piezas'] = 1, $h['mayorGramos'] = $celda],
            };
        } elseif (array_is_list($celda)) {
            $h['medida'] = array_sum($celda);
            $h['piezas'] = count($celda);
            $h['mayorMm'] = $celda === [] ? 0 : max($celda);
        } else {
            $h['peso'] = $celda['g'] ?? 0;
            $h['piezas'] = $celda['p'] ?? 1;
            // La pieza mayor: la apuntada o, con una sola pieza, el peso entero.
            $h['mayorGramos'] = max($celda['mayor'] ?? 0, ($h['piezas'] === 1) ? $h['peso'] : 0);
        }
        $h['valor'] = match ($criterio) {
            'medida' => $h['medida'],
            'piezas' => $h['piezas'],
            default => $h['peso'],
        };
        $h['bolo'] = $h['peso'] === 0 && $h['medida'] === 0 && $h['piezas'] === 0;

        return $h;
    }

    /** Lo que decide un empate (más es mejor); «comparten» o «promedio» no deciden nada. */
    private static function desempate(array $h, string $criterio, string $desempate): int
    {
        return match ($desempate) {
            'compartido', 'promedio' => 0,
            'pieza_mayor' => $criterio === 'medida' ? $h['mayorMm'] : $h['mayorGramos'],
            'peso' => $h['peso'],
            'menos_piezas' => -$h['piezas'],
            default => $h['piezas'],
        };
    }

    private static function desempateGeneral(array $f, array $cfg): int|float
    {
        return match ($cfg['desempate_general']) {
            'peso' => $f['peso'],
            'medida' => $f['medida'],
            'mejor_manga' => $f['mejorManga'] === null ? -PHP_INT_MAX : -$f['mejorManga'],
            'pieza_mayor' => $cfg['criterio'] === 'medida' ? $f['mayorMm'] : $f['mayorGramos'],
            'menos_piezas' => -$f['piezas'],
            'piezas' => $f['piezas'],
            default => 0,
        };
    }

    /** Ordena de mejor a peor por la clave y numera compartiendo los empates exactos (1, 1, 3). */
    private static function numerar(array $filas, callable $clave): array
    {
        uasort($filas, fn ($a, $b) => $clave($b) <=> $clave($a));
        $puesto = 0;
        $i = 0;
        $anterior = null;
        foreach ($filas as $nombre => $f) {
            $i++;
            $actual = $clave($f);
            if ($actual !== $anterior) {
                $puesto = $i;
                $anterior = $actual;
            }
            $filas[$nombre]['puesto'] = $puesto;
        }

        return $filas;
    }

    /** Por puestos: lo que se lleva alguien en una manga (su puesto, el promedio del grupo empatado, o el bolo). */
    private static function puntosEnManga(string $nombre, array $clasif, array $cfg): int|float
    {
        $fila = $clasif[$nombre];
        if ($fila['bolo']) {
            $n = count($clasif);
            $c = count(array_filter($clasif, fn ($f) => ! $f['bolo']));

            return match ($cfg['bolo']) {
                'primer_libre' => $c + 1,
                'ultimo' => $n,
                'fijo' => $cfg['puntos_bolo'],
                'ausencia' => self::costeAusencia($clasif, $cfg),
                default => (($c + 1) + $n) / 2,
            };
        }
        $empatados = count(array_filter($clasif, fn ($f) => $f['puesto'] === $fila['puesto']));
        if ($cfg['desempate'] === 'promedio' && $empatados > 1) {
            return $fila['puesto'] + ($empatados - 1) / 2;
        }

        return $fila['puesto'];
    }

    /** Por puestos: lo que cuesta no ir a una manga. */
    private static function costeAusencia(array $clasif, array $cfg): int
    {
        return $cfg['puntos_no_asistencia'] > 0 ? $cfg['puntos_no_asistencia'] : count($clasif) + 1;
    }

    /**
     * Qué mangas se descartan (índices): las N peores entre las candidatas.
     *  - Por puestos: la que más puntos cuesta, ausencias incluidas si la sección lo dice.
     *  - Suma lo pescado: primero las ausencias (si se descartan), luego la que menos aporta.
     *  A igualdad, la más antigua.
     */
    private static function descartadas(array $aporte, array $pescada, array $cfg): array
    {
        if ($cfg['descartes'] <= 0) {
            return [];
        }
        $candidatas = array_keys(array_filter($aporte, fn ($v, $m) => $pescada[$m] || $cfg['descartes_ausencias'], ARRAY_FILTER_USE_BOTH));

        if ($cfg['sistema'] === 'puestos') {
            usort($candidatas, fn ($a, $b) => [$aporte[$b], $a] <=> [$aporte[$a], $b]); // más puntos peor; a igualdad, la más antigua
        } else {
            $ausencias = array_values(array_filter($candidatas, fn ($m) => ! $pescada[$m]));
            $pescadas = array_values(array_filter($candidatas, fn ($m) => $pescada[$m]));
            usort($pescadas, fn ($a, $b) => [$aporte[$a], $a] <=> [$aporte[$b], $b]); // menos aporta peor; a igualdad, la más antigua
            $candidatas = [...$ausencias, ...$pescadas];
        }

        return array_slice($candidatas, 0, $cfg['descartes']);
    }
}
