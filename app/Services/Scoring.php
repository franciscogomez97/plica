<?php

namespace App\Services;

use App\Models\Manga;
use App\Models\Participacion;
use App\Models\Seccion;
use App\Models\Temporada;
use Illuminate\Support\Collection;

/**
 * ============================================================
 *  PUNTUACIÓN — configurable por temporada (v1)
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
 * Los rankings son SIEMPRE por sección. No existe ranking general.
 * ============================================================
 */
class Scoring
{
    /**
     * Clasificación de una manga, agrupada por sección.
     *
     * @return Collection<int, object{nombre: string, criterio: string, filas: Collection}>
     */
    public static function clasificacionManga(Manga $manga): Collection
    {
        $participaciones = $manga->participacions()
            ->with(['socio', 'seccion', 'capturas'])
            ->get();

        return static::agruparPorSeccion($participaciones)
            ->map(function (object $grupo) {
                $grupo->filas = static::ordenarYNumerar(
                    $grupo->participaciones->map(fn (Participacion $p) => static::fila($p)),
                    $grupo->criterio,
                );
                unset($grupo->participaciones);

                return $grupo;
            });
    }

    /** Ranking de temporada por sección, según la configuración de la temporada. */
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

                $grupo->sistema = $sistema;
                $grupo->filas = $sistema === Seccion::SISTEMA_PUESTOS
                    ? static::rankingPorPuestos($grupo->participaciones, $grupo->criterio, $descartes)
                    : static::rankingAcumulado($grupo->participaciones, $grupo->criterio, $puntosParticipacion, $descartes);
                unset($grupo->participaciones, $grupo->seccion);

                return $grupo;
            });
    }

    // ------------------------------------------------------------------
    //  Sistemas de ranking
    // ------------------------------------------------------------------

    private static function rankingAcumulado(Collection $participaciones, string $criterio, int $puntosParticipacion, int $descartes): Collection
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
            ->values()
            ->sortBy([['puntos', 'desc'], ['piezas', 'desc']])
            ->values();

        return static::numerar($filas);
    }

    private static function rankingPorPuestos(Collection $participaciones, string $criterio, int $descartes): Collection
    {
        // Puesto de cada socio en cada manga de esta sección.
        $porManga = $participaciones->groupBy('manga_id')->map(
            fn (Collection $deManga) => static::ordenarYNumerar(
                $deManga->map(fn (Participacion $p) => static::fila($p)),
                $criterio,
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
            ->values()
            ->sortBy([['puntos', 'asc'], ['peso', 'desc'], ['medida', 'desc']])
            ->values();

        return static::numerar($filas);
    }

    // ------------------------------------------------------------------
    //  Piezas comunes
    // ------------------------------------------------------------------

    /** Agrupa por sección y ordena los grupos (Sin sección al final). */
    private static function agruparPorSeccion(Collection $participaciones): Collection
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

    private static function fila(Participacion $p): object
    {
        return (object) [
            'socio' => $p->socio,
            'piezas' => $p->piezasTotal(),
            'peso' => $p->pesoTotal(),
            'medida' => $p->medidaTotal(),
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

    private static function ordenarYNumerar(Collection $filas, string $criterio): Collection
    {
        $orden = match ($criterio) {
            Seccion::CRITERIO_MEDIDA => [['medida', 'desc'], ['piezas', 'desc']],
            Seccion::CRITERIO_PIEZAS => [['piezas', 'desc'], ['peso', 'desc']],
            default => [['peso', 'desc'], ['piezas', 'desc']],
        };

        return static::numerar($filas->sortBy($orden)->values());
    }

    private static function numerar(Collection $filas): Collection
    {
        return $filas->map(function (object $fila, int $i) {
            $fila->puesto = $i + 1;

            return $fila;
        });
    }

    // ------------------------------------------------------------------
    //  Formato
    // ------------------------------------------------------------------

    /** Valor que manda en una fila según el criterio de su sección. */
    public static function valorPrincipal(string $criterio, object $fila): string
    {
        return match ($criterio) {
            Seccion::CRITERIO_MEDIDA => static::formatMedida($fila->medida),
            Seccion::CRITERIO_PIEZAS => $fila->piezas.($fila->piezas === 1 ? ' pieza' : ' piezas'),
            default => static::formatPeso($fila->peso),
        };
    }

    /** Dato secundario de una fila (complementa al principal). */
    public static function valorSecundario(string $criterio, object $fila): string
    {
        return match ($criterio) {
            Seccion::CRITERIO_PIEZAS => $fila->peso > 0 ? static::formatPeso($fila->peso) : '',
            default => $fila->piezas.($fila->piezas === 1 ? ' pieza' : ' piezas'),
        };
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
