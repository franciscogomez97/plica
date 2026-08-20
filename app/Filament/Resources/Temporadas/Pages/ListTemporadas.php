<?php

namespace App\Filament\Resources\Temporadas\Pages;

use App\Filament\Resources\Temporadas\TemporadaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTemporadas extends ListRecords
{
    protected static string $resource = TemporadaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
