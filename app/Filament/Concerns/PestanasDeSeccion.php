<?php

namespace App\Filament\Concerns;

use App\Models\Seccion;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Pestañas por sección arriba de Socios y de Mangas: «Todos» y una por sección
 * del club (Orilla · Pato · Embarcación). Cada admin suele llevar una sección:
 * la pestaña se recuerda en su sesión y, desde ella, lo que se crea ya es de
 * esa sección. No son permisos (eso será «admin de sección»): evitan el error,
 * no lo impiden.
 */
trait PestanasDeSeccion
{
    /** Cómo se filtra la tabla por una sección. */
    abstract protected function consultaDeSeccion(Builder $query, Seccion $seccion): Builder;

    /** Nombre de la pestaña que lo enseña todo («Todos» los socios, «Todas» las mangas). */
    abstract protected function etiquetaDeTodas(): string;

    /** @return array<string, Tab> */
    public function getTabs(): array
    {
        $secciones = $this->seccionesDelClub();

        // Con una sola sección (o ninguna) no hay nada que filtrar.
        if ($secciones->count() < 2) {
            return [];
        }

        $tabs = ['todas' => Tab::make('todas')->label($this->etiquetaDeTodas())];

        foreach ($secciones as $seccion) {
            $tabs[$seccion->slug] = Tab::make($seccion->slug)
                ->label($seccion->nombre)
                ->modifyQueryUsing(fn (Builder $query): Builder => $this->consultaDeSeccion($query, $seccion));
        }

        return $tabs;
    }

    /** La última pestaña en la que estuvo este admin, si sigue existiendo. */
    public function getDefaultActiveTab(): string|int|null
    {
        $tabs = $this->getCachedTabs();
        $recordada = session($this->clavePestanaEnSesion());

        if (is_string($recordada) && array_key_exists($recordada, $tabs)) {
            return $recordada;
        }

        return array_key_first($tabs);
    }

    public function updatedActiveTab(): void
    {
        parent::updatedActiveTab();

        session([$this->clavePestanaEnSesion() => $this->activeTab]);
    }

    /** La sección de la pestaña activa; null en «Todos». */
    public function seccionActiva(): ?Seccion
    {
        if (blank($this->activeTab) || $this->activeTab === 'todas') {
            return null;
        }

        return $this->seccionesDelClub()->firstWhere('slug', $this->activeTab);
    }

    /**
     * A qué sección va lo que se dé de alta: la de la pestaña o, si el club solo
     * tiene una (sin pestañas), esa.
     */
    public function seccionPorDefecto(): ?Seccion
    {
        return $this->seccionActiva() ?? ($this->seccionesDelClub()->count() === 1 ? $this->seccionesDelClub()->first() : null);
    }

    /** @return Collection<int, Seccion> */
    protected function seccionesDelClub(): Collection
    {
        return $this->seccionesDelClub ??= Seccion::query()
            ->where('club_id', auth()->user()->club_id)
            ->orderBy('nombre')
            ->get();
    }

    private function clavePestanaEnSesion(): string
    {
        return 'plica.pestana.'.static::class.'.'.auth()->user()->club_id;
    }
}
