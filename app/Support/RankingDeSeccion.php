<?php

namespace App\Support;

use App\Models\Club;
use App\Models\Seccion;
use App\Services\Compartir;
use App\Services\Scoring;

/**
 * Todo lo que necesita la página de ranking de una sección: la ve el público
 * en /c/{club}/{seccion} y el admin en su panel (mismo parcial, mismos datos).
 * Un solo sitio para calcularlo: si cambia algo, cambia en los dos.
 */
class RankingDeSeccion
{
    /** @return array<string, mixed> variables del parcial public.partials.seccion-ranking */
    public static function datos(Club $club, Seccion $seccion): array
    {
        $temporada = $club->temporadaActiva();

        $cuadro = $temporada ? Scoring::cuadroSeccion($temporada, $seccion) : null;
        $grupo = $temporada
            ? Scoring::rankingTemporada($temporada)->firstWhere('seccionId', $seccion->id)
            : null;

        $ultimaManga = $cuadro?->mangas->last();
        $clasifUltima = $ultimaManga
            ? Scoring::clasificacionManga($ultimaManga)->first(fn (object $g) => $g->seccion?->id === $seccion->id)
            : null;

        return [
            'club' => $club,
            'seccion' => $seccion,
            'temporada' => $temporada,
            'grupo' => $grupo,
            'cuadro' => $cuadro,
            'ultimaManga' => $ultimaManga,
            'clasifUltima' => $clasifUltima,
            'url' => $seccion->urlPublica(),
            'texto' => $grupo && $temporada ? Compartir::textoRanking($club, $temporada, $grupo) : null,
        ];
    }
}
