<?php

namespace App\Filament\Resources\Mangas\Schemas;

use App\Models\Manga;
use App\Models\Seccion;
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
                    ->placeholder('Elige la sección')
                    ->helperText('Toda manga es de una sección: los apuntados compiten en ella.')
                    // Viniendo de «crear sección» (?seccion=ID) la sección ya viene puesta.
                    ->default(function (): ?int {
                        $id = (int) request()->query('seccion');

                        return $id > 0
                            ? Seccion::query()->where('club_id', auth()->user()->club_id)->whereKey($id)->value('id')
                            : null;
                    })
                    ->required(),
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
                TextInput::make('ubicacion_url')
                    ->label('Ubicación (enlace a Google Maps)')
                    ->placeholder('https://maps.app.goo.gl/…')
                    ->helperText('Pega el enlace del punto de encuentro. Saldrá en la convocatoria y en la página de la manga.')
                    ->url()
                    ->maxLength(500)
                    ->nullable(),
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
