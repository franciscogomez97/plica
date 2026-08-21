<?php

namespace App\Filament\App\Pages;

use App\Models\Club;
use App\Models\Manga;
use App\Models\Socio;
use App\Models\Temporada;
use App\Services\Scoring;
use Filament\Pages\Dashboard;
use Illuminate\Support\Collection;

class Inicio extends Dashboard
{
    protected string $view = 'filament.app.inicio';

    protected static ?string $title = 'Mi club';

    public function getHeading(): string
    {
        return $this->getClub()?->nombre ?? 'Mi club';
    }

    public function getSubheading(): ?string
    {
        return $this->getTemporada()?->nombre;
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
        return $this->getClub()?->temporadaActiva();
    }

    /** @return Collection<int, Manga> */
    public function getProximasMangas(): Collection
    {
        return $this->getTemporada()
            ?->mangas()
            ->where('estado', Manga::ESTADO_PROGRAMADA)
            ->orderBy('fecha')
            ->get() ?? collect();
    }

    public function getRanking(): Collection
    {
        $temporada = $this->getTemporada();

        return $temporada ? Scoring::rankingTemporada($temporada) : collect();
    }

    public function getUltimaManga(): ?Manga
    {
        return $this->getTemporada()
            ?->mangas()
            ->where('estado', Manga::ESTADO_CELEBRADA)
            ->orderByDesc('fecha')
            ->first();
    }

    public function getClasificacionUltimaManga(): Collection
    {
        $manga = $this->getUltimaManga();

        return $manga ? Scoring::clasificacionManga($manga) : collect();
    }
}
