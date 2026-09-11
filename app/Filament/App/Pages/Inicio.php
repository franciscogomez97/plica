<?php

namespace App\Filament\App\Pages;

use App\Models\Club;
use App\Models\Manga;
use App\Models\Socio;
use App\Models\Temporada;
use App\Services\Scoring;
use Filament\Pages\Dashboard;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

class Inicio extends Dashboard
{
    protected string $view = 'filament.app.inicio';

    protected static ?string $title = 'Mi club';

    public function getHeading(): string|Htmlable
    {
        $club = $this->getClub();

        if ($club === null) {
            return 'Mi club';
        }

        $logo = $club->logoUrl();

        return new HtmlString(
            '<span style="display:inline-flex; align-items:center; gap:.75rem">'
            .($logo ? '<img src="'.e($logo).'" alt="" style="height:3rem; width:3rem; object-fit:contain; border-radius:.75rem">' : '')
            .e($club->nombre)
            .'</span>'
        );
    }

    public function getSubheading(): ?string
    {
        return $this->getTemporada()?->nombre;
    }

    public function ocultarGuia(): void
    {
        auth()->user()->forceFill(['guia_completada_at' => now()])->save();
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

    /** @return Collection<int, Manga> */
    public function getProximasMangas(): Collection
    {
        return $this->getTemporada()
            ?->mangas()
            ->with('seccion')
            ->withCount('confirmacions')
            ->where('estado', Manga::ESTADO_PROGRAMADA)
            ->orderBy('fecha')
            ->get() ?? collect();
    }

    /**
     * «Asistiré»: el socio marca (o quita) que irá a una manga. Es solo para
     * que el admin sepa con quién contar; la asistencia real la pasa el admin.
     */
    public function confirmar(int $mangaId): void
    {
        $socio = $this->getSocio();
        $manga = $this->getTemporada()
            ?->mangas()
            ->where('estado', Manga::ESTADO_PROGRAMADA)
            ->find($mangaId);

        if ($socio === null || ! $socio->activo || $manga === null) {
            return;
        }

        $manga->alternarConfirmacion($socio);
    }

    public function getRanking(): Collection
    {
        return once(function () {
            $temporada = $this->getTemporada();

            return $temporada ? Scoring::rankingTemporada($temporada) : collect();
        });
    }

    public function getUltimaManga(): ?Manga
    {
        return once(fn () => $this->getTemporada()
            ?->mangas()
            ->where('estado', Manga::ESTADO_CELEBRADA)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->first());
    }

    public function getClasificacionUltimaManga(): Collection
    {
        return once(function () {
            $manga = $this->getUltimaManga();

            return $manga ? Scoring::clasificacionManga($manga) : collect();
        });
    }
}
