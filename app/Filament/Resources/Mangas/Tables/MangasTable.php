<?php

namespace App\Filament\Resources\Mangas\Tables;

use App\Filament\Resources\Mangas\MangaResource;
use App\Models\Manga;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MangasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // Fila tipo app: apilada en móvil, horizontal en escritorio.
                Split::make([
                    Stack::make([
                        TextColumn::make('nombre')
                            ->weight(FontWeight::SemiBold)
                            ->searchable(),
                        TextColumn::make('fecha')
                            ->formatStateUsing(fn (Manga $record): string => $record->fecha->format('d/m/Y')
                                .($record->lugar ? ' · '.$record->lugar : ''))
                            ->color('gray'),
                    ])->space(1),
                    TextColumn::make('temporada.nombre')
                        ->color('gray')
                        ->grow(false)
                        ->visibleFrom('lg'),
                    TextColumn::make('participacions_count')
                        ->counts('participacions')
                        ->formatStateUsing(fn (int $state): string => $state === 1 ? '1 participante' : "{$state} participantes")
                        ->color('gray')
                        ->grow(false)
                        ->visibleFrom('lg'),
                    TextColumn::make('estado')
                        ->badge()
                        ->state(fn (Manga $record): string => $record->pendienteDeGestion() ? 'pendiente' : $record->estado)
                        ->color(fn (string $state): string => match ($state) {
                            Manga::ESTADO_CELEBRADA => 'success',
                            'pendiente' => 'danger',
                            default => 'warning',
                        })
                        ->formatStateUsing(fn (string $state): string => $state === 'pendiente' ? 'Por gestionar' : ucfirst($state))
                        ->grow(false),
                ])->from('md'),
            ])
            ->defaultSort('fecha', 'desc')
            // Tocar la fila = gestionar la manga (y allí, «Ver clasificación»).
            ->recordUrl(fn (Manga $record): string => MangaResource::getUrl('edit', ['record' => $record]));
    }
}
