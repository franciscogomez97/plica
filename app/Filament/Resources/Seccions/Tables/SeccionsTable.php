<?php

namespace App\Filament\Resources\Seccions\Tables;

use App\Filament\Resources\Seccions\SeccionResource;
use App\Models\Seccion;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SeccionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // Una sola línea por sección; las reglas, donde caben.
                Split::make([
                    TextColumn::make('nombre')
                        ->weight(FontWeight::SemiBold)
                        ->searchable(),
                    TextColumn::make('reglas')
                        ->state(fn (Seccion $record): string => implode(' · ', array_filter([
                            $record->puntos_participacion > 0 ? "{$record->puntos_participacion} pts por participar" : null,
                            $record->descartes > 0 ? ($record->descartes === 1 ? '1 descarte' : "{$record->descartes} descartes") : null,
                        ])))
                        ->color('gray')
                        ->grow(false)
                        ->visibleFrom('md'),
                    TextColumn::make('criterio')
                        ->badge()
                        ->color(fn (string $state): string => match ($state) {
                            Seccion::CRITERIO_MEDIDA => 'info',
                            Seccion::CRITERIO_PIEZAS => 'warning',
                            default => 'success',
                        })
                        ->formatStateUsing(fn (string $state): string => Seccion::CRITERIOS[$state] ?? $state)
                        ->grow(false),
                ]),
            ])
            ->defaultSort('nombre')
            ->recordUrl(fn (Seccion $record): string => SeccionResource::getUrl('edit', ['record' => $record]))
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->tooltip('Editar'),
                DeleteAction::make()
                    ->iconButton()
                    ->tooltip('Borrar')
                    // Una sección con pesajes o mangas no se borra: protege el historial.
                    ->visible(fn (Seccion $record): bool => $record->participacions()->doesntExist()
                        && $record->mangas()->doesntExist()),
            ]);
    }
}
