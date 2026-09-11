<?php

namespace App\Filament\Resources\Seccions\Tables;

use App\Filament\Resources\Seccions\SeccionResource;
use App\Models\Seccion;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SeccionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount(['participacions', 'mangas']))
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
                        ->tooltip(fn (Seccion $record): string => $record->resumenReglas())
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
                    ->visible(fn (Seccion $record): bool => $record->participacions_count === 0
                        && $record->mangas_count === 0),
                // La papelera no desaparece sin más: si no se puede borrar, se explica por qué.
                Action::make('protegida')
                    ->iconButton()
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('gray')
                    ->tooltip('No se puede borrar: tiene historial')
                    ->visible(fn (Seccion $record): bool => $record->participacions_count > 0
                        || $record->mangas_count > 0)
                    ->modalHeading(fn (Seccion $record): string => "«{$record->nombre}» no se puede borrar")
                    ->modalDescription(fn (Seccion $record): string => 'Tiene '
                        .($record->participacions_count === 1 ? '1 pesaje' : "{$record->participacions_count} pesajes")
                        .' y '
                        .($record->mangas_count === 1 ? '1 manga' : "{$record->mangas_count} mangas")
                        .'. Borrarla dejaría ese historial sin sección. Si ya no se usa, cámbiale el nombre o simplemente no crees más mangas en ella: el historial se conserva.')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Entendido'),
            ]);
    }
}
