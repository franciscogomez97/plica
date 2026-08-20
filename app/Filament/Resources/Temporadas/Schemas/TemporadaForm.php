<?php

namespace App\Filament\Resources\Temporadas\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TemporadaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nombre')
                    ->label('Nombre')
                    ->placeholder('Temporada 2026')
                    ->required(),
                Toggle::make('activa')
                    ->label('Temporada activa')
                    ->default(true),
            ]);
    }
}
