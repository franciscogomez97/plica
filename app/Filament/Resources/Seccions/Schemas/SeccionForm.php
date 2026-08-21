<?php

namespace App\Filament\Resources\Seccions\Schemas;

use App\Models\Seccion;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SeccionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nombre')
                    ->label('Nombre')
                    ->placeholder('Bass orilla, lucio pato…')
                    ->required(),
                Radio::make('criterio')
                    ->label('Se compite por')
                    ->options([
                        Seccion::CRITERIO_PESO => 'Peso',
                        Seccion::CRITERIO_MEDIDA => 'Medida',
                        Seccion::CRITERIO_PIEZAS => 'Nº de piezas',
                    ])
                    ->descriptions([
                        Seccion::CRITERIO_MEDIDA => 'Captura y suelta: puntúan los centímetros.',
                    ])
                    ->default(Seccion::CRITERIO_PESO)
                    ->required(),
                // El ranking de temporada es siempre por suma total (gana quien
                // más acumula). El sistema «por puestos» sigue en Scoring,
                // desactivado del formulario: nadie lo usaba. Para reactivarlo,
                // re-añadir aquí el radio de sistema_puntuacion.
                TextInput::make('puntos_participacion')
                    ->label('Puntos por participar')
                    ->helperText('Se suman por manga pescada. 0 = no se usan.')
                    ->numeric()
                    ->minValue(0)
                    ->default(0),
                TextInput::make('descartes')
                    ->label('Descartes')
                    ->helperText('Peores mangas que no cuentan al año. 0 = cuentan todas.')
                    ->numeric()
                    ->minValue(0)
                    ->default(0),
            ]);
    }
}
