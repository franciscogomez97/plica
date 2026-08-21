<?php

namespace App\Filament\Resources\Temporadas\Tables;

use App\Models\Temporada;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TemporadasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable(),
                IconColumn::make('activa')
                    ->label('Activa')
                    ->boolean(),
                TextColumn::make('mangas_count')
                    ->label('Mangas')
                    ->counts('mangas'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    // Una temporada con mangas no se borra: protege el historial.
                    ->visible(fn (Temporada $record): bool => $record->mangas()->doesntExist()),
            ]);
    }
}
