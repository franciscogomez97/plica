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
 *  - 'acumulado': total = Σ valor bruto + participación × puntos_participacion
 *    + mangas no pescadas × puntos_no_asistencia (0 por defecto; puede ser
 *    negativo). Gana quien MÁS suma.
 *  - 'puestos': cada manga da tantos puntos como tu puesto (1º = 1). Los
 *    empatados (según el desempate de la sección) comparten el mejor puesto
 *    o se reparten el promedio (desempate 'promedio': 18,5 y 18,5). No participar cuesta
 *    puntos_no_asistencia si está puesto (p. ej. socios + 1) y, si no,
 *    último + 1 de esa manga. Gana quien MENOS suma.
 *
 * Descartes (los dos sistemas): se ignoran las N peores mangas de cada socio.
 * descartes_ausencias decide si una manga no pescada puede ser una de ellas
 * (faltar es la peor manga) o si solo se descartan mangas pescadas.
 *
 * Por SECCIÓN (desempate): piezas | peso | pieza_mayor, o ninguno ('compartido'
 * y, por puestos, 'promedio'). Si tras el desempate siguen iguales, comparten
 * puesto (1º, 1º, 3º). Nunca decide el azar ni el orden de la base de datos.
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
                $puntosNoAsistencia = $grupo->seccion?->puntos_no_asistencia ?? 0;
                $descartes = $grupo->seccion?->descartes ?? 0;
                $descartesAusencias = (bool) ($grupo->seccion?->descartes_ausencias ?? false);
                $desempate = static::desempateDe($grupo->seccion, $grupo->criterio);
                $puestosEmpate = $desempate; // por puestos: 'promedio' reparte; cualquier otro comparte

                $grupo->sistema = $sistema;
                $grupo->puntosParticipacion = $puntosParticipacion;
                $grupo->puntosNoAsistencia = $puntosNoAsistencia;
                $grupo->puestosEmpate = $puestosEmpate;
                $grupo->seccionId = $grupo->seccion?->id;
                $grupo->seccionSlug = $grupo->seccion?->slug;
                $grupo->numMangas = $grupo->participaciones->pluck('manga_id')->unique()->count();
                $grupo->reglas = $grupo->seccion?->resumenReglas() ?? Seccion::resumenReglasDe($grupo->criterio);
                $grupo->filas = $sistema === Seccion::SISTEMA_PUESTOS
                    ? static::rankingPorPuestos($grupo->participaciones, $grupo->criterio, $descartes, $desempate, $puestosEmpate, $puntosNoAsistencia, $descartesAusencias)
                    : static::rankingAcumulado($grupo->participaciones, $grupo->criterio, $puntosParticipacion, $descartes, $desempate, $puntosNoAsistencia, $descartesAusencias);
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
        $descartesAusencias = (bool) $seccion->descartes_ausencias;
        $puntosParticipacion = (int) $seccion->puntos_participacion;
        $puntosNoAsistencia = (int) $seccion->puntos_no_asistencia;
        $desempate = static::desempateDe($seccion, $criterio);
        $puestosEmpate = $desempate;

        $mangas = $participaciones->pluck('manga')->unique('id')->sortBy(['fecha', 'id'])->values();

        // Puesto (y, por puestos, puntos) de cada socio en cada manga, misma ordenación
        // que su clasificación; quién hizo la pieza mayor; y qué cuesta no ir a cada una.
        $puestos = [];
        $puntosManga = [];
        $ausentes = [];
        $mayores = [];
        foreach ($participaciones->groupBy('manga_id') as $mangaId => $deManga) {
            $clasif = static::ordenarYNumerar($deManga->map(fn (Participacion $p) => static::fila($p)), $criterio, $desempate);
            $porPuesto = static::puntosPorPuesto($clasif, $puestosEmpate);
            foreach ($clasif as $fila) {
                $puestos[$mangaId][$fila->socio->id] = $fila->puesto;
                $puntosManga[$mangaId][$fila->socio->id] = $porPuesto[$fila->socio->id];
            }
            $ausentes[$mangaId] = static::puntosDeAusente($clasif, $puntosNoAsistencia);
            $mayores[$mangaId] = static::piezaMayorDe($deManga, $criterio)?->socio->id;
        }

        // El orden y los puntos son EXACTAMENTE los del ranking de temporada.
        $ranking = $sistema === Seccion::SISTEMA_PUESTOS
            ? static::rankingPorPuestos($participaciones, $criterio, $descartes, $desempate, $puestosEmpate, $puntosNoAsistencia, $descartesAusencias)
            : static::rankingAcumulado($participaciones, $criterio, $puntosParticipacion, $descartes, $desempate, $puntosNoAsistencia, $descartesAusencias);

        $mangaIds = $mangas->pluck('id')->all();

        $filas = $ranking->map(function (object $fila) use ($participaciones, $mangas, $mangaIds, $puestos, $puntosManga, $ausentes, $mayores, $criterio, $sistema, $descartes, $descartesAusencias) {
            $deSocio = $participaciones->where('socio_id', $fila->socio->id);

            // Mangas descartadas: EXACTAMENTE las mismas que en el ranking (también las no pescadas si la sección lo dice).
            $porManga = $sistema === Seccion::SISTEMA_PUESTOS
                ? static::porMangaPuestos($deSocio, $mangaIds, $puntosManga, $ausentes)
                : static::porMangaAcumulado($deSocio, $mangaIds, $criterio);
            $descartadas = static::descartadas($porManga, $descartes, $descartesAusencias, $sistema === Seccion::SISTEMA_PUESTOS);
            $fila->descartadas = $descartadas; // ids de manga, incluidas las no pescadas

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
                    'puntos' => $puntosManga[$manga->id][$p->socio_id] ?? null, // por puestos: lo que suma esa manga
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
            'puntosNoAsistencia' => $puntosNoAsistencia,
            'puestosEmpate' => $puestosEmpate,
            'ausentePorManga' => $ausentes, // por puestos: lo que cuesta no ir a cada manga
            'reglas' => $seccion->resumenReglas(),
            'mangas' => $mangas,
            'filas' => $filas,
            'piezaMayor' => static::piezaMayorDe($participaciones, $criterio),
        ];
    }

    // ------------------------------------------------------------------
    //  Sistemas de ranking
    // ------------------------------------------------------------------

    private static function rankingAcumulado(Collection $participaciones, string $criterio, int $puntosParticipacion, int $descartes, string $desempate, int $puntosNoAsistencia = 0, bool $descartesAusencias = false): Collection
    {
        // Mangas celebradas de la sección: las que un socio del ranking no pescó son
        // sus «no asistencias» (solo cuenta para quien ha pescado alguna).
        $mangaIds = $participaciones->pluck('manga_id')->unique()->values()->all();

        $filas = $participaciones
            ->groupBy('socio_id')
            ->map(function (Collection $deSocio) use ($criterio, $puntosParticipacion, $descartes, $puntosNoAsistencia, $descartesAusencias, $mangaIds) {
                $porManga = static::porMangaAcumulado($deSocio, $mangaIds, $criterio);
                $descartadas = static::descartadas($porManga, $descartes, $descartesAusencias, false);
                $cuentan = array_diff_key($porManga, array_flip($descartadas));

                // Lo pescado en las mangas que cuentan, más la asistencia de las pescadas
                // que cuentan, más lo que dé (o quite) la sección por cada no pescada que cuenta.
                $pescadas = array_filter($cuentan, fn (array $m) => $m['pescada']);
                $noPescadas = count($cuentan) - count($pescadas);
                $puntos = (int) array_sum(array_column($pescadas, 'puntos'))
                    + count($pescadas) * $puntosParticipacion
                    + $noPescadas * $puntosNoAsistencia;

                return static::filaAgregada($deSocio, $puntos);
            })
            ->values();

        // Más puntos delante; empate → desempate de la sección; si sigue igual, mismo puesto.
        $clave = fn (object $f): array => [$f->puntos, static::valorDesempate($f, $criterio, $desempate)];

        return static::numerar($filas->sort(fn ($a, $b) => $clave($b) <=> $clave($a))->values(), $clave);
    }

    private static function rankingPorPuestos(Collection $participaciones, string $criterio, int $descartes, string $desempate, string $empate = Seccion::DESEMPATE_COMPARTIDO, int $puntosNoAsistencia = 0, bool $descartesAusencias = true): Collection
    {
        // Puntos de cada socio en cada manga de esta sección (su puesto, o el promedio
        // si empata y así lo quiere la sección) y lo que cuesta no ir a cada una.
        $puntosManga = [];
        $ausentes = [];
        foreach ($participaciones->groupBy('manga_id') as $mangaId => $deManga) {
            $clasif = static::ordenarYNumerar($deManga->map(fn (Participacion $p) => static::fila($p)), $criterio, $desempate);
            $puntosManga[$mangaId] = static::puntosPorPuesto($clasif, $empate);
            $ausentes[$mangaId] = static::puntosDeAusente($clasif, $puntosNoAsistencia);
        }
        $mangaIds = array_keys($puntosManga);

        $filas = $participaciones
            ->groupBy('socio_id')
            ->map(function (Collection $deSocio) use ($puntosManga, $ausentes, $mangaIds, $descartes, $descartesAusencias) {
                $porManga = static::porMangaPuestos($deSocio, $mangaIds, $puntosManga, $ausentes);
                $descartadas = static::descartadas($porManga, $descartes, $descartesAusencias, true);
                $cuentan = array_diff_key($porManga, array_flip($descartadas));

                $total = array_sum(array_column($cuentan, 'puntos'));

                return static::filaAgregada($deSocio, $total == (int) $total ? (int) $total : (float) $total);
            })
            ->values();

        // Menos puntos delante; empate → desempate de la sección (más es mejor); si sigue igual, mismo puesto.
        $clave = fn (object $f): array => [-$f->puntos, static::valorDesempate($f, $criterio, $desempate)];

        return static::numerar($filas->sort(fn ($a, $b) => $clave($b) <=> $clave($a))->values(), $clave);
    }

    // ------------------------------------------------------------------
    //  Mangas de un socio y descartes (lo mismo para el ranking y el cuadro)
    // ------------------------------------------------------------------

    /**
     * Las mangas de la sección vistas por un socio, en «suma lo pescado»:
     * manga_id => ['puntos' => lo pescado, 'pescada' => si fue]. No ir = 0.
     *
     * @return array<int, array{puntos: int|float, pescada: bool}>
     */
    private static function porMangaAcumulado(Collection $deSocio, array $mangaIds, string $criterio): array
    {
        $porManga = [];
        foreach ($mangaIds as $mangaId) {
            $p = $deSocio->firstWhere('manga_id', $mangaId);
            $porManga[$mangaId] = $p === null
                ? ['puntos' => 0, 'pescada' => false]
                : ['puntos' => static::valor($p, $criterio), 'pescada' => true];
        }

        return $porManga;
    }

    /**
     * Lo mismo en «por puestos»: los puntos de su puesto o lo que cuesta no ir.
     *
     * @return array<int, array{puntos: int|float, pescada: bool}>
     */
    private static function porMangaPuestos(Collection $deSocio, array $mangaIds, array $puntosManga, array $ausentes): array
    {
        $socioId = $deSocio->first()->socio_id;

        $porManga = [];
        foreach ($mangaIds as $mangaId) {
            $porManga[$mangaId] = isset($puntosManga[$mangaId][$socioId])
                ? ['puntos' => $puntosManga[$mangaId][$socioId], 'pescada' => true]
                : ['puntos' => $ausentes[$mangaId], 'pescada' => false];
        }

        return $porManga;
    }

    /**
     * Qué mangas se descartan: las N peores entre las candidatas, que son todas
     * (faltar es la peor manga) o solo las pescadas, según la sección. En
     * «suma lo pescado» la peor es la de menos puntos; por puestos, la de más.
     *
     * @param  array<int, array{puntos: int|float, pescada: bool}>  $porManga
     * @return int[] ids de manga descartadas
     */
    private static function descartadas(array $porManga, int $descartes, bool $incluirAusencias, bool $menosEsMejor): array
    {
        if ($descartes <= 0) {
            return [];
        }

        $candidatas = $incluirAusencias ? $porManga : array_filter($porManga, fn (array $m) => $m['pescada']);

        // De peor a mejor; a igualdad, la más antigua primero (orden de las mangas).
        uasort($candidatas, fn (array $a, array $b) => $menosEsMejor
            ? $b['puntos'] <=> $a['puntos']
            : $a['puntos'] <=> $b['puntos']);

        return array_slice(array_keys($candidatas), 0, $descartes);
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

    /**
     * Puntos por puesto de una clasificación ya numerada: el puesto de cada uno o,
     * con empates «promedio», la media de los puestos que ocupa el grupo empatado
     * (dos en el 18 → 18,5 cada uno; ocho en el 22 → 25,5 cada uno).
     *
     * @return array<int, int|float> socio_id => puntos
     */
    public static function puntosPorPuesto(Collection $clasif, string $empate): array
    {
        $porPuesto = $clasif->groupBy('puesto');

        $puntos = [];
        foreach ($clasif as $fila) {
            $tamano = $porPuesto->get($fila->puesto)->count();
            $valor = $empate === Seccion::DESEMPATE_PROMEDIO && $tamano > 1
                ? $fila->puesto + ($tamano - 1) / 2
                : $fila->puesto;
            $puntos[$fila->socio->id] = $valor == (int) $valor ? (int) $valor : $valor;
        }

        return $puntos;
    }

    /** Lo que cuesta no ir a una manga: lo que diga la sección o, si no, el último de esa manga más uno. */
    public static function puntosDeAusente(Collection $clasif, int $puntosNoAsistencia): int
    {
        return $puntosNoAsistencia > 0 ? $puntosNoAsistencia : $clasif->count() + 1;
    }

    private static function filaAgregada(Collection $deSocio, int|float $puntos): object
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

    /** Lo que decide un empate, según la sección (más es mejor). Sin desempate, nada lo decide: comparten. */
    private static function valorDesempate(object $fila, string $criterio, string $desempate): int
    {
        return match ($desempate) {
            Seccion::DESEMPATE_COMPARTIDO, Seccion::DESEMPATE_PROMEDIO => 0,
            Seccion::DESEMPATE_PIEZA_MAYOR => $criterio === Seccion::CRITERIO_MEDIDA ? $fila->mayorMm : $fila->mayorGramos,
            Seccion::DESEMPATE_PESO => $fila->peso,
            Seccion::DESEMPATE_MENOS_PIEZAS => -$fila->piezas, // menos piezas es mejor
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
    /** 1234 -> "1.234" · 18.5 -> "18,5" (los promedios de empates por puestos). */
    public static function formatPuntos(int|float $puntos): string
    {
        return $puntos == (int) $puntos
            ? number_format($puntos, 0, ',', '.')
            : number_format($puntos, 1, ',', '.');
    }
}
