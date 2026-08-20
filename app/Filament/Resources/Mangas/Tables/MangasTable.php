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
                    ->label('Temporada'),
                TextColumn::make('fecha')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('lugar')
                    ->label('Lugar')
                    ->placeholder('—'),
                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => $state === Manga::ESTADO_CELEBRADA ? 'success' : 'warning')
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                TextColumn::make('participacions_count')
                    ->label('Participantes')
                    ->counts('participacions'),
            ])
            ->defaultSort('fecha', 'desc')
            ->recordActions([
                Action::make('clasificacion')
                    ->label('Clasificación')
                    ->icon('heroicon-o-trophy')
                    ->url(fn (Manga $record): string => MangaResource::getUrl('clasificacion', ['record' => $record])),
                EditAction::make(),
            ]);
    }
}
