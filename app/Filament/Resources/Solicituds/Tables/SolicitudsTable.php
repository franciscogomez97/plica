<?php

namespace App\Filament\Resources\Solicituds\Tables;

use Filament\Actions\DeleteAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SolicitudsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Split::make([
                    Stack::make([
                        TextColumn::make('club_nombre')
                            ->weight(FontWeight::SemiBold),
                        TextColumn::make('email')
                            ->color('gray'),
                        TextColumn::make('mensaje')
                            ->limit(60)
                            ->color('gray'),
                    ])->space(1),
                    TextColumn::make('created_at')
                        ->dateTime('d/m/Y H:i')
                        ->color('gray')
                        ->grow(false),
                ])->from('md'),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                DeleteAction::make()
                    ->iconButton()
                    ->tooltip('Borrar'),
            ]);
    }
}
