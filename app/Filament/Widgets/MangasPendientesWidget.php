<?php

namespace App\Filament\Widgets;

use App\Models\Manga;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/** Aviso persistente en el dashboard: mangas celebradas pendientes de gestionar. */
class MangasPendientesWidget extends Widget
{
    protected string $view = 'filament.widgets.mangas-pendientes';

    protected static ?int $sort = -10;

    /** Sin lazy-load: el aviso debe estar en el primer render. */
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Manga::pendientesDeGestion()->exists();
    }

    public function getMangas(): Collection
    {
        return Manga::pendientesDeGestion()
            ->with('temporada')
            ->withCount('participacions')
            ->orderBy('fecha')
            ->get();
    }
}
