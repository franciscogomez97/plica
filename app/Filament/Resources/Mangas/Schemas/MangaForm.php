<?php

namespace App\Filament\Resources\Mangas\Schemas;

use App\Models\Manga;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class MangaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('temporada_id')
                    ->label('Temporada')
                    ->relationship(
                        name: 'temporada',
                        titleAttribute: 'nombre',
                        modifyQueryUsing: fn (Builder $query) => $query->where('club_id', auth()->user()->club_id),
                    )
                    ->default(fn (): ?int => auth()->user()->club?->temporadaActiva()?->id)
                    ->required(),
                Select::make('seccion_id')
                    ->label('Sección')
                    ->relationship(
                        name: 'seccion',
                        titleAttribute: 'nombre',
                        modifyQueryUsing: fn (Builder $query) => $query->where('club_id', auth()->user()->club_id),
                    )
                    ->placeholder('Todo el club')
                    ->nullable(),
                TextInput::make('nombre')
                    ->label('Nombre')
                    ->placeholder('1ª Manga')
                    ->required(),
                DatePicker::make('fecha')
                    ->label('Fecha')
                    ->required(),
                TextInput::make('lugar')
                    ->label('Lugar')
                    ->placeholder('Embalse, río, tramo…'),
                Radio::make('estado')
                    ->label('Estado')
                    ->options([
                        Manga::ESTADO_PROGRAMADA => 'Programada',
                        Manga::ESTADO_CELEBRADA => 'Celebrada',
                    ])
                    ->descriptions([
                        Manga::ESTADO_CELEBRADA => 'Cuenta para el ranking de la temporada.',
                    ])
                    ->inline()
                    ->default(Manga::ESTADO_PROGRAMADA)
                    ->hiddenOn('create') // una manga nueva siempre nace programada
                    ->required(),
                Textarea::make('notas')
                    ->label('Notas')
                    ->rows(3),
            ]);
    }
}
