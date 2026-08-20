<?php

namespace App\Filament\Resources\Temporadas\Pages;

use App\Filament\Resources\Temporadas\TemporadaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTemporada extends EditRecord
{
    protected static string $resource = TemporadaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
