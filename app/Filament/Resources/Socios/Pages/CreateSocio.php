<?php

namespace App\Filament\Resources\Socios\Pages;

use App\Filament\Resources\Socios\SocioResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSocio extends CreateRecord
{
    protected static bool $canCreateAnother = false;

    protected static string $resource = SocioResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['club_id'] = auth()->user()->club_id;

        return $data;
    }
}
