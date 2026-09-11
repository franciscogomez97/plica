<?php

namespace App\Filament\Pages;

use App\Models\Seccion;
use App\Models\Temporada;
use App\Services\Scoring;
use Filament\Pages\Page;

/**
 * Ranking detallado de una sección: el cuadro tipo hoja de cálculo con los
 * pescadores en filas y las mangas de la temporada en columnas. Quién ganó
 * cada manga, qué sacó cada uno y quién va ganando, de un vistazo.
 */
class RankingSeccion extends Page
{
    protected string $view = 'filament.ranking-seccion';

    protected static ?string $slug = 'ranking/{seccion}';

    protected static bool $shouldRegisterNavigation = false;

    public int $seccionId;

    public function mount(int|string $seccion): void
    {
        $this->seccionId = $this->getSeccion((int) $seccion)->id;
    }

    /** Solo secciones del club del admin: cualquier otra, 404. */
    public function getSeccion(?int $id = null): Seccion
    {
        return Seccion::query()
            ->where('club_id', auth()->user()->club_id)
            ->findOrFail($id ?? $this->seccionId);
    }

    public function getTemporada(): ?Temporada
    {
        return once(fn () => auth()->user()->club?->temporadaActiva());
    }

    public function getTitle(): string
    {
        return 'Ranking detallado — '.$this->getSeccion()->nombre;
    }

    public function getSubheading(): ?string
    {
        return $this->getTemporada()?->nombre;
    }

    public function getBreadcrumbs(): array
    {
        return [
            Ranking::getUrl() => 'Ranking',
            $this->getSeccion()->nombre,
        ];
    }

    public function getCuadro(): ?object
    {
        return once(function () {
            $temporada = $this->getTemporada();

            return $temporada ? Scoring::cuadroSeccion($temporada, $this->getSeccion()) : null;
        });
    }
}
