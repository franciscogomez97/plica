<?php

namespace App\Filament\Resources\Solicituds\Tables;

use Filament\Actions\DeleteAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SolicitudsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // Una sola línea por solicitud.
                Split::make([
                    TextColumn::make('club_nombre')
                        ->weight(FontWeight::SemiBold),
                    TextColumn::make('email')
                        ->color('gray')
                        ->grow(false)
                        ->visibleFrom('md'),
                    TextColumn::make('mensaje')
                        ->limit(40)
                        ->color('gray')
                        ->grow(false)
                        ->visibleFrom('lg'),
                    TextColumn::make('created_at')
                        ->dateTime('d/m/Y H:i')
                        ->color('gray')
                        ->grow(false),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                DeleteAction::make()
                    ->iconButton()
                    ->tooltip('Borrar'),
            ]);
    }
}
