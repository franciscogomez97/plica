<?php

namespace App\Filament\Resources\Mangas\Pages;

use App\Filament\Concerns\PestanasDeSeccion;
use App\Filament\Resources\Mangas\MangaResource;
use App\Models\Seccion;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ListMangas extends ListRecords
{
    use PestanasDeSeccion;

    protected static string $resource = MangaResource::class;

    /** @var Collection<int, Seccion>|null */
    protected ?Collection $seccionesDelClub = null;

    protected function getHeaderActions(): array
    {
        return [
            // Desde la pestaña de una sección, la manga nueva ya es de esa sección.
            CreateAction::make()
                ->url(fn (): string => MangaResource::getUrl('create', array_filter(['seccion' => $this->seccionPorDefecto()?->id]))),
        ];
    }

    protected function consultaDeSeccion(Builder $query, Seccion $seccion): Builder
    {
        return $query->where('seccion_id', $seccion->id);
    }

    protected function etiquetaDeTodas(): string
    {
        return 'Todas';
    }
}
