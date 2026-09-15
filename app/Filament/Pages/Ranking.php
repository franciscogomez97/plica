<?php

namespace App\Filament\Pages;

use App\Models\Manga;
use App\Models\Participacion;
use App\Models\Seccion;
use App\Models\Temporada;
use App\Support\RankingDeSeccion;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Url;

/**
 * Ranking de la temporada activa, por sección: una pestaña por sección (como en
 * Mangas) y, dentro, lo mismo que ve cualquiera en la página pública: quién va
 * primero, la pieza mayor del año, el cuadro manga a manga y la última manga.
 * Los rankings son SIEMPRE por sección: no hay pestaña «Todas».
 */
class Ranking extends Page
{
    protected string $view = 'filament.ranking';

    protected static ?string $title = 'Ranking';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static ?int $navigationSort = 25;

    /** Pestaña activa (slug de la sección), en la URL para que se pueda enlazar. */
    #[Url(as: 'seccion')]
    public ?string $seccion = null;

    /** @var Collection<int, Seccion>|null */
    protected ?Collection $seccionesDelClub = null;

    public function mount(): void
    {
        $secciones = $this->getSecciones();

        if ($this->seccion === null || ! $secciones->contains('slug', $this->seccion)) {
            $recordada = session($this->clavePestanaEnSesion());
            $this->seccion = is_string($recordada) && $secciones->contains('slug', $recordada)
                ? $recordada
                : $this->seccionConMasActividad()?->slug;
        }

        if ($this->seccion !== null) {
            session([$this->clavePestanaEnSesion() => $this->seccion]);
        }
    }

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

    /** Las secciones del club, en el mismo orden que las pestañas de Mangas. */
    public function getSecciones(): Collection
    {
        return $this->seccionesDelClub ??= Seccion::query()
            ->where('club_id', auth()->user()->club_id)
            ->orderBy('nombre')
            ->get();
    }

    public function getSeccionActiva(): ?Seccion
    {
        return $this->getSecciones()->firstWhere('slug', $this->seccion);
    }

    /** Los mismos datos que la página pública de la sección activa (parcial compartido). */
    public function getDatos(): ?array
    {
        return once(function () {
            $seccion = $this->getSeccionActiva();

            return $seccion ? RankingDeSeccion::datos(auth()->user()->club, $seccion) : null;
        });
    }

    /**
     * Sin pestaña recordada: la sección con más participaciones en mangas
     * celebradas esta temporada (la que el admin quiere ver al entrar); a igualdad,
     * la primera por nombre.
     */
    private function seccionConMasActividad(): ?Seccion
    {
        $secciones = $this->getSecciones();
        $temporada = $this->getTemporada();

        if ($temporada === null || $secciones->isEmpty()) {
            return $secciones->first();
        }

        $participaciones = Participacion::query()
            ->whereHas('manga', fn ($q) => $q->where('temporada_id', $temporada->id)->where('estado', Manga::ESTADO_CELEBRADA))
            ->selectRaw('seccion_id, count(*) as n')
            ->groupBy('seccion_id')
            ->pluck('n', 'seccion_id');

        return $secciones->sortByDesc(fn (Seccion $s) => (int) ($participaciones[$s->id] ?? 0))->first();
    }

    public static function urlDeSeccion(Seccion $seccion): string
    {
        return static::getUrl(['seccion' => $seccion->slug]);
    }

    private function clavePestanaEnSesion(): string
    {
        return 'plica.pestana.'.static::class.'.'.auth()->user()->club_id;
    }
}
