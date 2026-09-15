<?php

namespace App\Filament\Resources\Equipos\Pages;

use App\Filament\Resources\Equipos\EquipoResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateEquipo extends CreateRecord
{
    protected static string $resource = EquipoResource::class;

    /** Un equipo es siempre de la temporada activa. */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $temporada = auth()->user()->club?->temporadaActiva();

        if ($temporada === null) {
            Notification::make()->title('Hace falta una temporada activa para crear equipos')->danger()->send();
            $this->halt();
        }

        $data['temporada_id'] = $temporada->id;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return EquipoResource::getUrl('index');
    }
}
