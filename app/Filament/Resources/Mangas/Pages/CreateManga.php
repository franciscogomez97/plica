<?php

namespace App\Filament\Resources\Mangas\Pages;

use App\Filament\Resources\Mangas\MangaResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Arr;

/**
 * El calendario de una sección se mete de una tacada: «Crear y añadir otra»
 * deja puestas la temporada y la sección, y solo hay que teclear nombre y
 * fecha de la siguiente. (Es el único formulario con dos botones de crear,
 * a propósito.)
 */
class CreateManga extends CreateRecord
{
    protected static string $resource = MangaResource::class;

    protected function preserveFormDataWhenCreatingAnother(array $data): array
    {
        return Arr::only($data, ['temporada_id', 'seccion_id']);
    }

    protected function getCreateAnotherFormAction(): Action
    {
        return parent::getCreateAnotherFormAction()->label('Crear y añadir otra');
    }

    /** Tras crear una manga se ve el calendario entero, no la ficha de la recién creada. */
    protected function getRedirectUrl(): string
    {
        return MangaResource::getUrl();
    }
}
