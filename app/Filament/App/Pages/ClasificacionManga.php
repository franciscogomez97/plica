<?php

namespace App\Filament\App\Pages;

use App\Models\Club;
use App\Models\Manga;
use App\Models\Socio;
use App\Services\Scoring;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/** Clasificación completa de una manga para el socio, con su puesto resaltado. */
class ClasificacionManga extends Page
{
    protected string $view = 'filament.app.clasificacion-manga';

    protected static ?string $slug = 'manga/{manga}';

    protected static bool $shouldRegisterNavigation = false;

    public int $mangaId;

    public function mount(int|string $manga): void
    {
        $this->mangaId = $this->getManga((int) $manga)->id;
    }

    /** Solo mangas del club del socio: cualquier otra, 404. */
    public function getManga(?int $id = null): Manga
    {
        return Manga::query()
            ->whereHas('temporada', fn ($q) => $q->where('club_id', auth()->user()->club_id))
            ->with('seccion')
            ->findOrFail($id ?? $this->mangaId);
    }

    public function getClub(): ?Club
    {
        return auth()->user()->club;
    }

    public function getSocio(): ?Socio
    {
        return auth()->user()->socio;
    }

    public function getTitle(): string
    {
        return $this->getManga()->nombre;
    }

    public function getSubheading(): ?string
    {
        $manga = $this->getManga();

        return implode(' · ', array_filter([
            $manga->fecha->format('d/m/Y'),
            $manga->lugar,
            $manga->estado === Manga::ESTADO_CELEBRADA ? null : 'clasificación provisional',
        ]));
    }

    public function getGrupos(): Collection
    {
        return Scoring::clasificacionManga($this->getManga());
    }
}
