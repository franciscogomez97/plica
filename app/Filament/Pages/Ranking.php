<?php

namespace App\Filament\Pages;

use App\Models\Manga;
use App\Models\Temporada;
use App\Services\Scoring;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/** Ranking de la temporada activa, por sección — la misma lista que ve el socio. */
class Ranking extends Page
{
    protected string $view = 'filament.ranking';

    protected static ?string $title = 'Ranking';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static ?int $navigationSort = 25;

    public function getSubheading(): ?string
    {
        $temporada = $this->getTemporada();

        if ($temporada === null) {
            return null;
        }

        $celebradas = $temporada->mangas()->where('estado', Manga::ESTADO_CELEBRADA)->count();

        return $temporada->nombre.' · '.($celebradas === 1 ? '1 manga celebrada' : "{$celebradas} mangas celebradas");
    }

    public function getTemporada(): ?Temporada
    {
        return once(fn () => auth()->user()->club?->temporadaActiva());
    }

    public function getGrupos(): Collection
    {
        return once(function () {
            $temporada = $this->getTemporada();

            return $temporada ? Scoring::rankingTemporada($temporada) : collect();
        });
    }
}
