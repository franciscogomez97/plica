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
                    ->placeholder('Temporada '.now()->year)
                    // Viniendo del aviso de cambio de temporada (?nombre=…) ya viene puesto.
                    ->default(fn (): ?string => request()->query('nombre') ?: null)
                    ->required(),
                Toggle::make('activa')
                    ->label('Temporada activa')
                    ->default(true),
            ]);
    }
}
