<?php

namespace App\Filament\Resources\Socios\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SocioForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nombre')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(120),
                TextInput::make('email')
                    ->label('Email (opcional)')
                    ->helperText('Opcional.')
                    ->email()
                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? mb_strtolower(trim($state)) : null)
                    ->nullable(),
                Toggle::make('activo')
                    ->label('Activo')
                    ->default(true),
            ]);
    }
}
