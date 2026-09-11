<?php

namespace App\Filament\Widgets;

use App\Models\Manga;
use App\Services\Scoring;
use Filament\Widgets\Widget;

/** Resumen operativo del club: próxima manga, socios y líderes por sección. */
class ResumenClubWidget extends Widget
{
    protected string $view = 'filament.widgets.resumen-club';

    protected static ?int $sort = -8;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->club?->socios()->exists() ?? false;
    }

    public function getDatos(): object
    {
        $club = auth()->user()->club;
        $temporada = $club->temporadaActiva();

        $proxima = $temporada?->mangas()
            ->where('estado', Manga::ESTADO_PROGRAMADA)
            ->whereDate('fecha', '>=', today())
            ->orderBy('fecha')
            ->first();

        return (object) [
            'proxima' => $proxima,
            'sociosActivos' => $club->socios()->where('activo', true)->count(),
            'celebradas' => $temporada?->mangas()->where('estado', Manga::ESTADO_CELEBRADA)->count() ?? 0,
            'lideres' => $temporada
                ? Scoring::rankingTemporada($temporada)->map(fn (object $g) => (object) [
                    'seccion' => $g->nombre,
                    'lider' => $g->filas->first(),
                    'criterio' => $g->criterio,
                    'sistema' => $g->sistema,
                ])
                : collect(),
        ];
    }
}
