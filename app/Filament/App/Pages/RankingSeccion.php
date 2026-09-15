<?php

namespace App\Filament\App\Pages;

use App\Models\Club;
use App\Models\Manga;
use App\Models\Seccion;
use App\Models\Socio;
use App\Models\Temporada;
use App\Services\Scoring;
use Filament\Pages\Page;

/**
 * Ranking completo de una sección para el socio: la lista entera, el cuadro
 * manga a manga y la última manga de la sección. Desde el Inicio se llega con
 * «Ver ranking completo».
 */
class RankingSeccion extends Page
{
    protected string $view = 'filament.app.ranking-seccion';

    protected static ?string $slug = 'ranking/{seccion}';

    protected static bool $shouldRegisterNavigation = false;

    public int $seccionId;

    public function mount(int|string $seccion): void
    {
        $this->seccionId = $this->getSeccion((int) $seccion)->id;
    }

    /** Solo secciones del club del socio: cualquier otra, 404. */
    public function getSeccion(?int $id = null): Seccion
    {
        return Seccion::query()
            ->where('club_id', auth()->user()->club_id)
            ->findOrFail($id ?? $this->seccionId);
    }

    public function getClub(): ?Club
    {
        return auth()->user()->club;
    }

    public function getSocio(): ?Socio
    {
        return auth()->user()->socio;
    }

    public function getTemporada(): ?Temporada
    {
        return once(fn () => $this->getClub()?->temporadaActiva());
    }

    public function getTitle(): string
    {
        return 'Ranking '.$this->getSeccion()->nombre;
    }

    public function getSubheading(): ?string
    {
        return $this->getTemporada()?->nombre;
    }

    /** Los mismos datos que la página pública de la sección (parcial compartido). */
    public function getDatos(): array
    {
        return once(fn () => \App\Support\RankingDeSeccion::datos($this->getClub(), $this->getSeccion()));
    }

    public function getCuadro(): ?object
    {
        return once(function () {
            $temporada = $this->getTemporada();

            return $temporada ? Scoring::cuadroSeccion($temporada, $this->getSeccion()) : null;
        });
    }

    /** El grupo de esta sección en el ranking de temporada (misma lista que el admin). */
    public function getGrupo(): ?object
    {
        $temporada = $this->getTemporada();

        return once(fn () => $temporada
            ? Scoring::rankingTemporada($temporada)->firstWhere('seccionId', $this->seccionId)
            : null);
    }

    /** Última manga celebrada en la que compitió esta sección, con su clasificación. */
    public function getUltima(): ?object
    {
        $manga = $this->getCuadro()?->mangas->last();

        if (! $manga instanceof Manga) {
            return null;
        }

        $grupo = Scoring::clasificacionManga($manga)->first(fn (object $g) => $g->seccion?->id === $this->seccionId);

        return $grupo ? (object) ['manga' => $manga, 'grupo' => $grupo] : null;
    }
}
