<?php

namespace App\Filament\Resources\Seccions\Tables;

use App\Filament\Resources\Seccions\SeccionResource;
use App\Models\Seccion;
use Filament\Actions\DeleteAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SeccionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Split::make([
                    Stack::make([
                        TextColumn::make('nombre')
                            ->weight(FontWeight::SemiBold)
                            ->searchable(),
                        TextColumn::make('reglas')
                            ->state(fn (Seccion $record): string => implode(' · ', array_filter([
                                $record->sistema_puntuacion === Seccion::SISTEMA_PUESTOS ? 'Por puestos' : 'Suma total',
                                $record->puntos_participacion > 0 ? "{$record->puntos_participacion} pts por participar" : null,
                                $record->descartes > 0 ? ($record->descartes === 1 ? '1 descarte' : "{$record->descartes} descartes") : null,
                            ])))
                            ->color('gray'),
                    ])->space(1),
                    TextColumn::make('criterio')
                        ->badge()
                        ->color(fn (string $state): string => match ($state) {
                            Seccion::CRITERIO_MEDIDA => 'info',
                            Seccion::CRITERIO_PIEZAS => 'warning',
                            default => 'success',
                        })
                        ->formatStateUsing(fn (string $state): string => Seccion::CRITERIOS[$state] ?? $state)
                        ->grow(false),
                ])->from('md'),
            ])
            ->defaultSort('nombre')
            // Tocar la fila = editar las reglas de la sección.
            ->recordUrl(fn (Seccion $record): string => SeccionResource::getUrl('edit', ['record' => $record]))
            ->recordActions([
                DeleteAction::make()
                    ->iconButton()
                    ->tooltip('Borrar')
                    // Una sección con pesajes no se borra: su historial se recolocaría.
                    ->visible(fn (Seccion $record): bool => $record->participacions()->doesntExist()),
            ]);
    }
}
