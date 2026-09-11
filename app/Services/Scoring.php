<?php

namespace App\Services;

use App\Models\Manga;
use App\Models\Participacion;
use App\Models\Seccion;
use App\Models\Temporada;
use Illuminate\Support\Collection;

/**
 * ============================================================
 *  PUNTUACIÓN — configurable por sección
 * ============================================================
 * Los hechos (participaciones y capturas) nunca se tocan:
 * cambiar la configuración recalcula todo al vuelo.
 *
 * Por SECCIÓN (criterio): peso | medida | piezas → cómo se
 * ordena la clasificación de cada manga.
 *
 * Por SECCIÓN (ranking de temporada) — cada sección define su sistema:
 *  - 'acumulado': total = Σ valor bruto + participación × puntos_participacion.
 *    Gana quien MÁS suma. Descartes: se ignoran las N peores mangas (menor valor).
 *  - 'puestos': cada manga da tantos puntos como tu puesto (1º = 1);
 *    no participar = último + 1 de esa manga. Gana quien MENOS suma.
 *    Descartes: se ignoran las N peores mangas (mayor puesto).
 *
 * Por SECCIÓN (desempate): piezas | peso | pieza_mayor. Si tras el desempate
 * siguen iguales, comparten puesto (1º, 1º, 3º). Nunca decide el azar ni el
 * orden de la base de datos.
 *
 * Pieza mayor: se calcula por manga y por temporada (siempre hay premio).
 *
 * Los rankings son SIEMPRE por sección. No existe ranking general.
 * ============================================================
 */
class Scoring
{
    /**
     * Clasificación de una manga, agrupada por sección.
     *
     * @return Collection<int, object{nombre: string, criterio: string, filas: Collection, piezaMayor: ?object}>
     */
    public static function clasificacionManga(Manga $manga): Collection
    {
        $participaciones = $manga->participacions()
            ->with(['socio', 'seccion', 'capturas', 'manga'])
            ->get();

        return static::agruparPorSeccion($participaciones)
            ->map(function (object $grupo) {
                $desempate = static::desempateDe($grupo->seccion, $grupo->criterio);

                $grupo->filas = static::ordenarYNumerar(
                    $grupo->participaciones->map(fn (Participacion $p) => static::fila($p)),
                    $grupo->criterio,
                    $desempate,
                );
                $grupo->piezaMayor = static::piezaMayorDe($grupo->participaciones, $grupo->criterio);
                unset($grupo->participaciones);

                return $grupo;
            });
    }

    /** Ranking de temporada por sección, según la configuración de cada sección. */
    public static function rankingTemporada(Temporada $temporada): Collection
    {
        $participaciones = Participacion::query()
            ->whereHas('manga', fn ($q) => $q
                ->where('temporada_id', $temporada->id)
                ->where('estado', Manga::ESTADO_CELEBRADA))
            ->with(['socio', 'seccion', 'capturas', 'manga'])
            ->get();

        return static::agruparPorSeccion($participaciones)
            ->map(function (object $grupo) {
                $sistema = $grupo->seccion?->sistema_puntuacion ?? Seccion::SISTEMA_ACUMULADO;
                $puntosParticipacion = $grupo->seccion?->puntos_participacion ?? 0;
                $descartes = $grupo->seccion?->descartes ?? 0;
                $desempate = static::desempateDe($grupo->seccion, $grupo->criterio);

                $grupo->sistema = $sistema;
                $grupo->puntosParticipacion = $puntosParticipacion;
                $grupo->seccionId = $grupo->seccion?->id;
                $grupo->seccionSlug = $grupo->seccion?->slug;
                $grupo->numMangas = $grupo->participaciones->pluck('manga_id')->unique()->count();
                $grupo->reglas = $grupo->seccion?->resumenReglas() ?? Seccion::resumenReglasDe($grupo->criterio);
                $grupo->filas = $sistema === Seccion::SISTEMA_PUESTOS
                    ? static::rankingPorPuestos($grupo->participaciones, $grupo->criterio, $descartes, $desempate)
                    : static::rankingAcumulado($grupo->participaciones, $grupo->criterio, $puntosParticipacion, $descartes, $desempate);
                $grupo->piezaMayor = static::piezaMayorDe($grupo->participaciones, $grupo->criterio);
                unset($grupo->participaciones, $grupo->seccion);

                return $grupo;
            });
    }

    /**
     * Cuadro de una sección en la temporada, tipo hoja de cálculo: una fila por
     * socio (en el orden del ranking, con los mismos puntos) y una columna por
     * manga celebrada, con lo pescado, el puesto en esa manga y si la manga
     * queda descartada, y quién hizo la pieza mayor de cada manga. Sirve para ver
     * quién ganó cada manga y quién va ganando.
     *
     * @return object{seccion: Seccion, nombre: string, criterio: string, sistema: string, puntosParticipacion: int, reglas: string, mangas: Collection<int, Manga>, filas: Collection<int, object>, piezaMayor: ?object}
     */
    public static function cuadroSeccion(Temporada $temporada, Seccion $seccion): object
    {
        $participaciones = Participacion::query()
            ->where('seccion_id', $seccion->id)
            ->whereHas('manga', fn ($q) => $q
                ->where('temporada_id', $temporada->id)
                ->where('estado', Manga::ESTADO_CELEBRADA))
            ->with(['socio', 'capturas', 'manga'])
            ->get();

        $criterio = $seccion->criterio ?? Seccion::CRITERIO_PESO;
        $sistema = $seccion->sistema_puntuacion ?? Seccion::SISTEMA_ACUMULADO;
        $descartes = (int) $seccion->descartes;
        $puntosParticipacion = (int) $seccion->puntos_participacion;
        $desempate = static::desempateDe($seccion, $criterio);

        $mangas = $participaciones->pluck('manga')->unique('id')->sortBy(['fecha', 'id'])->values();

        // Puesto de cada socio en cada manga (misma ordenación que su clasificación)
        // y quién hizo la pieza mayor de cada manga.
        $puestos = [];
        $mayores = [];
        foreach ($participaciones->groupBy('manga_id') as $mangaId => $deManga) {
            $clasif = static::ordenarYNumerar($deManga->map(fn (Participacion $p) => static::fila($p)), $criterio, $desempate);
            foreach ($clasif as $fila) {
                $puestos[$mangaId][$fila->socio->id] = $fila->puesto;
            }
            $mayores[$mangaId] = static::piezaMayorDe($deManga, $criterio)?->socio->id;
        }

        // El orden y los puntos son EXACTAMENTE los del ranking de temporada.
        $ranking = $sistema === Seccion::SISTEMA_PUESTOS
            ? static::rankingPorPuestos($participaciones, $criterio, $descartes, $desempate)
            : static::rankingAcumulado($participaciones, $criterio, $puntosParticipacion, $descartes, $desempate);

        $filas = $ranking->map(function (object $fila) use ($participaciones, $mangas, $puestos, $mayores, $criterio, $sistema, $descartes) {
            $deSocio = $participaciones->where('socio_id', $fila->socio->id);

            // Mangas descartadas: las N peores (menor valor; en «puestos», mayor puesto).
            $ordenadas = $sistema === Seccion::SISTEMA_PUESTOS
                ? $deSocio->sortBy(fn (Participacion $p) => $puestos[$p->manga_id][$p->socio_id] ?? PHP_INT_MAX)
                : $deSocio->sortByDesc(fn (Participacion $p) => static::valor($p, $criterio));
            $contadas = max($ordenadas->count() - $descartes, 0);
            $descartadas = $ordenadas->values()->slice($contadas)->pluck('manga_id')->all();

            $fila->celdas = [];
            foreach ($mangas as $manga) {
                $p = $deSocio->firstWhere('manga_id', $manga->id);

                $fila->celdas[$manga->id] = $p === null ? null : (object) [
                    'valor' => static::valor($p, $criterio),
                    'texto' => static::valorPrincipal($criterio, static::fila($p)),
                    'piezas' => $p->piezasTotal(),
                    'mayor' => static::piezaMayorTexto($criterio, static::fila($p)),
                    'mayorDeLaManga' => ($mayores[$manga->id] ?? null) === $p->socio_id,
                    'puesto' => $puestos[$manga->id][$p->socio_id] ?? null,
                    'descartada' => in_array($manga->id, $descartadas, true),
                ];
            }

            return $fila;
        });

        return (object) [
            'seccion' => $seccion,
            'nombre' => $seccion->nombre,
            'criterio' => $criterio,
            'sistema' => $sistema,
            'puntosParticipacion' => $puntosParticipacion,
            'reglas' => $seccion->resumenReglas(),
            'mangas' => $mangas,
            'filas' => $filas,
            'piezaMayor' => static::piezaMayorDe($participaciones, $criterio),
        ];
    }

    // ------------------------------------------------------------------
    //  Sistemas de ranking
    // ------------------------------------------------------------------

    private static function rankingAcumulado(Collection $participaciones, string $criterio, int $puntosParticipacion, int $descartes, string $desempate): Collection
    {
        $filas = $participaciones
            ->groupBy('socio_id')
            ->map(function (Collection $deSocio) use ($criterio, $puntosParticipacion, $descartes) {
                // Valor de cada manga pescada, de mejor a peor, aplicando descartes.
                $valores = $deSocio
                    ->map(fn (Participacion $p) => static::valor($p, $criterio))
                    ->sortDesc()
                    ->values();

                $contadas = max($valores->count() - $descartes, 0);
                $puntos = (int) $valores->take($contadas)->sum()
                    + $contadas * $puntosParticipacion;

                return static::filaAgregada($deSocio, $puntos);
            })
            ->values();

        // Más puntos delante; empate → desempate de la sección; si sigue igual, mismo puesto.
        $clave = fn (object $f): array => [$f->puntos, static::valorDesempate($f, $criterio, $desempate)];

        return static::numerar($filas->sort(fn ($a, $b) => $clave($b) <=> $clave($a))->values(), $clave);
    }

    private static function rankingPorPuestos(Collection $participaciones, string $criterio, int $descartes, string $desempate): Collection
    {
        // Puesto de cada socio en cada manga de esta sección.
        $porManga = $participaciones->groupBy('manga_id')->map(
            fn (Collection $deManga) => static::ordenarYNumerar(
                $deManga->map(fn (Participacion $p) => static::fila($p)),
                $criterio,
                $desempate,
            )->keyBy(fn (object $fila) => $fila->socio->id)
        );

        $filas = $participaciones
            ->groupBy('socio_id')
            ->map(function (Collection $deSocio) use ($porManga, $descartes) {
                $socioId = $deSocio->first()->socio_id;

                // Puestos en todas las mangas de la sección; ausente = último + 1.
                $puestos = $porManga
                    ->map(fn (Collection $clasif) => $clasif->has($socioId)
                        ? $clasif->get($socioId)->puesto
                        : $clasif->count() + 1)
                    ->sort() // de mejor (menor) a peor
                    ->values();

                $contadas = max($puestos->count() - $descartes, 0);
                $puntos = (int) $puestos->take($contadas)->sum();

                return static::filaAgregada($deSocio, $puntos);
            })
            ->values();

        // Menos puntos delante; empate → desempate de la sección (más es mejor); si sigue igual, mismo puesto.
        $clave = fn (object $f): array => [-$f->puntos, static::valorDesempate($f, $criterio, $desempate)];

        return static::numerar($filas->sort(fn ($a, $b) => $clave($b) <=> $clave($a))->values(), $clave);
    }

    // ------------------------------------------------------------------
    //  Piezas comunes
    // ------------------------------------------------------------------

    /** Agrupa por sección y ordena los grupos (Sin sección al final). También lo usa el pesaje rápido. */
    public static function agruparPorSeccion(Collection $participaciones): Collection
    {
        return $participaciones
            ->groupBy(fn (Participacion $p) => $p->seccion_id ?? 0)
            ->map(function (Collection $grupo) {
                $seccion = $grupo->first()->seccion;

                return (object) [
                    'nombre' => $seccion?->nombre ?? 'Sin sección',
                    'criterio' => $seccion?->criterio ?? Seccion::CRITERIO_PESO,
                    'seccion' => $seccion,
                    'participaciones' => $grupo,
                ];
            })
            ->sortBy(fn (object $g) => $g->nombre === 'Sin sección' ? 'zzz' : $g->nombre)
            ->values();
    }

    /**
     * La pieza mayor de un conjunto de participaciones (una manga o toda la
     * temporada): quién, cuánto y en qué manga. Null si nadie apuntó nada.
     */
    public static function piezaMayorDe(Collection $participaciones, string $criterio): ?object
    {
        $mejor = $participaciones
            ->map(fn (Participacion $p) => [
                'p' => $p,
                'valor' => $criterio === Seccion::CRITERIO_MEDIDA ? $p->piezaMayorMm() : $p->piezaMayorGramos(),
            ])
            ->filter(fn (array $x) => $x['valor'] > 0)
            ->sortByDesc('valor')
            ->first();

        if ($mejor === null) {
            return null;
        }

        return (object) [
            'socio' => $mejor['p']->socio,
            'manga' => $mejor['p']->manga,
            'valor' => $mejor['valor'],
            'texto' => $criterio === Seccion::CRITERIO_MEDIDA
                ? static::formatMedida($mejor['valor'])
                : static::formatPeso($mejor['valor']),
        ];
    }

    private static function desempateDe(?Seccion $seccion, string $criterio): string
    {
        return $seccion?->desempate ?? Seccion::desempatePorDefecto($criterio);
    }

    private static function fila(Participacion $p): object
    {
        return (object) [
            'socio' => $p->socio,
            'piezas' => $p->piezasTotal(),
            'peso' => $p->pesoTotal(),
            'medida' => $p->medidaTotal(),
            'mayorGramos' => $p->piezaMayorGramos(),
            'mayorMm' => $p->piezaMayorMm(),
            'plica' => $p->plica,
        ];
    }

    private static function filaAgregada(Collection $deSocio, int $puntos): object
    {
        return (object) [
            'socio' => $deSocio->first()->socio,
            'mangas' => $deSocio->count(),
            'piezas' => (int) $deSocio->sum(fn (Participacion $p) => $p->piezasTotal()),
            'peso' => (int) $deSocio->sum(fn (Participacion $p) => $p->pesoTotal()),
            'medida' => (int) $deSocio->sum(fn (Participacion $p) => $p->medidaTotal()),
            'mayorGramos' => (int) $deSocio->max(fn (Participacion $p) => $p->piezaMayorGramos()),
            'mayorMm' => (int) $deSocio->max(fn (Participacion $p) => $p->piezaMayorMm()),
            'puntos' => $puntos,
        ];
    }

    private static function valor(Participacion $p, string $criterio): int
    {
        return match ($criterio) {
            Seccion::CRITERIO_MEDIDA => $p->medidaTotal(),
            Seccion::CRITERIO_PIEZAS => $p->piezasTotal(),
            default => $p->pesoTotal(),
        };
    }

    private static function valorDeFila(object $fila, string $criterio): int
    {
        return match ($criterio) {
            Seccion::CRITERIO_MEDIDA => $fila->medida,
            Seccion::CRITERIO_PIEZAS => $fila->piezas,
            default => $fila->peso,
        };
    }

    /** Lo que decide un empate, según la sección (más es mejor). */
    private static function valorDesempate(object $fila, string $criterio, string $desempate): int
    {
        return match ($desempate) {
            Seccion::DESEMPATE_PIEZA_MAYOR => $criterio === Seccion::CRITERIO_MEDIDA ? $fila->mayorMm : $fila->mayorGramos,
            Seccion::DESEMPATE_PESO => $fila->peso,
            default => $fila->piezas,
        };
    }

    private static function ordenarYNumerar(Collection $filas, string $criterio, string $desempate): Collection
    {
        $clave = fn (object $f): array => [static::valorDeFila($f, $criterio), static::valorDesempate($f, $criterio, $desempate)];

        return static::numerar($filas->sort(fn ($a, $b) => $clave($b) <=> $clave($a))->values(), $clave);
    }

    /**
     * Numera puestos compartiendo los empates exactos (1º, 1º, 3º): dos filas
     * con la misma clave tienen el mismo puesto, y el siguiente salta.
     *
     * @param  callable(object): array  $clave
     */
    private static function numerar(Collection $filas, callable $clave): Collection
    {
        $anterior = null;
        $puesto = 0;

        return $filas->values()->map(function (object $fila, int $i) use (&$anterior, &$puesto, $clave) {
            $actual = $clave($fila);

            if ($actual !== $anterior) {
                $puesto = $i + 1;
                $anterior = $actual;
            }

            $fila->puesto = $puesto;

            return $fila;
        });
    }

    // ------------------------------------------------------------------
    //  Formato
    // ------------------------------------------------------------------

    /**
     * Los puntos de un ranking acumulado, en su unidad natural.
     * Solo tiene sentido sin puntos de participación (ahí puntos = suma pescada).
     */
    public static function valorRanking(string $criterio, int $puntos): string
    {
        return match ($criterio) {
            Seccion::CRITERIO_MEDIDA => $puntos > 0 ? static::formatMedida($puntos) : '—',
            Seccion::CRITERIO_PIEZAS => $puntos > 0 ? $puntos.($puntos === 1 ? ' pieza' : ' piezas') : '—',
            default => $puntos > 0 ? static::formatPeso($puntos) : '—',
        };
    }

    /** Valor que manda en una fila según el criterio de su sección. «—» si no pescó nada. */
    public static function valorPrincipal(string $criterio, object $fila): string
    {
        return match ($criterio) {
            Seccion::CRITERIO_MEDIDA => $fila->medida > 0 ? static::formatMedida($fila->medida) : '—',
            Seccion::CRITERIO_PIEZAS => $fila->piezas > 0 ? $fila->piezas.($fila->piezas === 1 ? ' pieza' : ' piezas') : '—',
            default => $fila->peso > 0 ? static::formatPeso($fila->peso) : '—',
        };
    }

    /** Dato secundario de una fila (complementa al principal), con la pieza mayor si la hay. */
    public static function valorSecundario(string $criterio, object $fila): string
    {
        $base = match ($criterio) {
            Seccion::CRITERIO_PIEZAS => $fila->peso > 0 ? static::formatPeso($fila->peso) : '',
            default => $fila->piezas.($fila->piezas === 1 ? ' pieza' : ' piezas'),
        };

        $mayor = static::piezaMayorTexto($criterio, $fila);

        return implode(' · ', array_filter([$base, $mayor !== '' ? "mayor {$mayor}" : '']));
    }

    /** La pieza mayor de una fila (de manga o de temporada) en su unidad; «» si no la hay. */
    public static function piezaMayorTexto(string $criterio, object $fila): string
    {
        if ($criterio === Seccion::CRITERIO_MEDIDA) {
            return ($fila->mayorMm ?? 0) > 0 ? static::formatMedida($fila->mayorMm) : '';
        }

        return ($fila->mayorGramos ?? 0) > 0 ? static::formatPeso($fila->mayorGramos) : '';
    }

    /** 3450 -> "3,450 kg" */
    public static function formatPeso(int $gramos): string
    {
        return number_format($gramos / 1000, 3, ',', '.').' kg';
    }

    /** 585 -> "58,5 cm" · 620 -> "62 cm" */
    public static function formatMedida(int $mm): string
    {
        $cm = number_format($mm / 10, 1, ',', '.');

        return rtrim(rtrim($cm, '0'), ',').' cm';
    }

    /** 6450 -> "6.450" */
    public static function formatPuntos(int $puntos): string
    {
        return number_format($puntos, 0, ',', '.');
    }
}
