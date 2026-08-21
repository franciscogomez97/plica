<?php

namespace App\Filament\Resources\Seccions\Tables;

use App\Models\Seccion;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SeccionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable(),
                TextColumn::make('criterio')
                    ->label('Criterio')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Seccion::CRITERIO_MEDIDA => 'info',
                        Seccion::CRITERIO_PIEZAS => 'warning',
                        default => 'success',
                    })
                    ->formatStateUsing(fn (string $state): string => Seccion::CRITERIOS[$state] ?? $state),
                TextColumn::make('sistema_puntuacion')
                    ->label('Ranking')
                    ->badge()
                    ->color(fn (string $state): string => $state === Seccion::SISTEMA_PUESTOS ? 'info' : 'success')
                    ->formatStateUsing(fn (string $state): string => $state === Seccion::SISTEMA_PUESTOS ? 'Por puestos' : 'Acumulado'),
                TextColumn::make('puntos_participacion')
                    ->label('Pts. participación')
                    ->numeric()
                    ->visibleFrom('md'),
                TextColumn::make('descartes')
                    ->label('Descartes')
                    ->numeric()
                    ->visibleFrom('md'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
