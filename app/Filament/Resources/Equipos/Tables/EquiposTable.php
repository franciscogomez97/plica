<?php

namespace App\Filament\Resources\Equipos\Tables;

use App\Filament\Resources\Equipos\EquipoResource;
use App\Filament\Resources\Equipos\Pages\ListEquipos;
use App\Models\Equipo;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EquiposTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // Una línea por equipo: cómo se llama (o quiénes son), sus socios y de qué sección.
                Split::make([
                    TextColumn::make('etiqueta')
                        ->state(fn (Equipo $record): string => $record->etiqueta())
                        ->weight(FontWeight::SemiBold)
                        ->searchable(query: fn ($query, string $search) => $query
                            ->where('nombre', 'like', "%{$search}%")
                            ->orWhereHas('socios', fn ($q) => $q->where('nombre', 'like', "%{$search}%"))),
                    // Con nombre propio, los socios van al lado en gris; sin nombre ya son la etiqueta.
                    TextColumn::make('miembros')
                        ->state(fn (Equipo $record): ?string => filled($record->nombre) ? $record->miembrosTexto() : null)
                        ->color('gray')
                        ->grow(false)
                        ->visibleFrom('sm'),
                    TextColumn::make('seccion.nombre')
                        ->badge()
                        ->color('info')
                        ->visible(fn (ListEquipos $livewire): bool => $livewire->seccionActiva() === null && $livewire->getCachedTabs() !== [])
                        ->grow(false),
                    TextColumn::make('cuantos')
                        ->state(fn (Equipo $record): string => $record->socios->count().' de '.$record->seccion->tamano_equipo)
                        ->badge()
                        ->color(fn (Equipo $record): string => $record->socios->count() >= $record->seccion->tamano_equipo ? 'success' : 'warning')
                        ->grow(false),
                ]),
            ])
            ->defaultSort('id')
            ->paginated(false)
            ->recordUrl(fn (Equipo $record): string => EquipoResource::getUrl('edit', ['record' => $record]))
            ->recordActions([
                EditAction::make()->iconButton()->tooltip('Editar'),
                DeleteAction::make()->iconButton()->tooltip('Borrar'),
            ])
            ->emptyStateHeading('Todavía no hay equipos')
            ->emptyStateDescription('Pega la lista de barcos o parejas con «Añadir varios», o crea el primero.');
    }
}
