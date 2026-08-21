<?php

namespace App\Filament\Resources\Mangas\Tables;

use App\Filament\Resources\Mangas\MangaResource;
use App\Models\Manga;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MangasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->label('Manga')
                    ->searchable(),
                TextColumn::make('temporada.nombre')
                    ->label('Temporada')
                    ->visibleFrom('md'),
                TextColumn::make('fecha')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('lugar')
                    ->label('Lugar')
                    ->placeholder('—')
                    ->visibleFrom('sm'),
                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->state(fn (Manga $record): string => $record->pendienteDeGestion() ? 'pendiente' : $record->estado)
                    ->color(fn (string $state): string => match ($state) {
                        Manga::ESTADO_CELEBRADA => 'success',
                        'pendiente' => 'danger',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn (string $state): string => $state === 'pendiente' ? 'Por gestionar' : ucfirst($state)),
                TextColumn::make('participacions_count')
                    ->label('Participantes')
                    ->counts('participacions')
                    ->visibleFrom('md'),
            ])
            ->defaultSort('fecha', 'desc')
            ->recordActions([
                Action::make('clasificacion')
                    ->label('Clasificación')
                    ->icon('heroicon-o-trophy')
                    ->url(fn (Manga $record): string => MangaResource::getUrl('clasificacion', ['record' => $record])),
                EditAction::make()
                    ->label('Gestionar'),
            ]);
    }
}
