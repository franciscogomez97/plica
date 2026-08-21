<?php

namespace App\Filament\Resources\Mangas\Tables;

use App\Filament\Resources\Mangas\MangaResource;
use App\Models\Manga;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MangasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('seccion'))
            ->columns([
                // Una sola línea por manga; el detalle aparece según cabe.
                Split::make([
                    TextColumn::make('nombre')
                        ->weight(FontWeight::SemiBold)
                        ->searchable(),
                    TextColumn::make('seccion.nombre')
                        ->badge()
                        ->color('gray')
                        ->grow(false)
                        ->visibleFrom('sm'),
                    TextColumn::make('lugar')
                        ->color('gray')
                        ->grow(false)
                        ->visibleFrom('md'),
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
                    TextColumn::make('fecha')
                        ->date('d/m/Y')
                        ->color('gray')
                        ->grow(false),
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
                ]),
            ])
            ->defaultSort('fecha', 'desc')
            // Tocar la fila = gestionar la manga (y allí, «Ver clasificación»).
            ->recordUrl(fn (Manga $record): string => MangaResource::getUrl('edit', ['record' => $record]))
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->tooltip('Gestionar'),
                DeleteAction::make()
                    ->iconButton()
                    ->tooltip('Borrar')
                    // Con pesajes dentro no se borra: primero habría que vaciarla.
                    ->visible(fn (Manga $record): bool => $record->participacions()->doesntExist()),
            ]);
    }
}
