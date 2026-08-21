<?php

namespace App\Filament\Resources\Temporadas\Tables;

use App\Filament\Resources\Temporadas\TemporadaResource;
use App\Models\Temporada;
use Filament\Actions\DeleteAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TemporadasTable
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
                        TextColumn::make('mangas_count')
                            ->counts('mangas')
                            ->formatStateUsing(fn (int $state): string => $state === 1 ? '1 manga' : "{$state} mangas")
                            ->color('gray'),
                    ])->space(1),
                    TextColumn::make('activa')
                        ->badge()
                        ->state(fn (Temporada $record): string => $record->activa ? 'Activa' : 'Cerrada')
                        ->color(fn (string $state): string => $state === 'Activa' ? 'success' : 'gray')
                        ->grow(false),
                ])->from('md'),
            ])
            // Tocar la fila = editar la temporada.
            ->recordUrl(fn (Temporada $record): string => TemporadaResource::getUrl('edit', ['record' => $record]))
            ->recordActions([
                DeleteAction::make()
                    ->iconButton()
                    ->tooltip('Borrar')
                    // Una temporada con mangas no se borra: protege el historial.
                    ->visible(fn (Temporada $record): bool => $record->mangas()->doesntExist()),
            ]);
    }
}
