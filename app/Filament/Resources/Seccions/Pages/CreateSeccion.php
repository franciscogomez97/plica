<?php

namespace App\Filament\Resources\Seccions\Pages;

use App\Filament\Resources\Mangas\MangaResource;
use App\Filament\Resources\Seccions\SeccionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSeccion extends CreateRecord
{
    protected static bool $canCreateAnother = false;

    protected static string $resource = SeccionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['club_id'] = auth()->user()->club_id;

        return $data;
    }

    /** Una sección sin calendario no sirve de nada: de aquí, a crear sus mangas. */
    protected function getRedirectUrl(): string
    {
        return MangaResource::getUrl('create', ['seccion' => $this->getRecord()->getKey()]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Sección creada. Ahora, las mangas de su calendario.';
    }
}
