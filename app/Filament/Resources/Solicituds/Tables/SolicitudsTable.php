<?php

namespace App\Filament\Resources\Solicituds\Tables;

use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SolicitudsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('club_nombre')
                    ->label('Club'),
                TextColumn::make('email')
                    ->label('Email'),
                TextColumn::make('mensaje')
                    ->label('Mensaje')
                    ->limit(60)
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Recibida')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                DeleteAction::make(),
            ]);
    }
}
