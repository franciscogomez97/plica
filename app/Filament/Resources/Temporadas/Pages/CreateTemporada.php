<?php

namespace App\Filament\Resources\Temporadas\Pages;

use App\Filament\Resources\Temporadas\TemporadaResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTemporada extends CreateRecord
{
    protected static string $resource = TemporadaResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['club_id'] = auth()->user()->club_id;

        return $data;
    }
}
