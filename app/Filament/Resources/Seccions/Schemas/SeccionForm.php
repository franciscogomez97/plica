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
                Radio::make('sistema_puntuacion')
                    ->label('Ranking de la temporada')
                    ->options([
                        Seccion::SISTEMA_ACUMULADO => 'Suma total',
                        Seccion::SISTEMA_PUESTOS => 'Por puestos',
                    ])
                    ->descriptions([
                        Seccion::SISTEMA_ACUMULADO => 'Se suma lo pescado en todas las mangas: gana quien más acumula.',
                        Seccion::SISTEMA_PUESTOS => '1º = 1 punto, 2º = 2… gana quien menos suma. No asistir cuenta como último +1.',
                    ])
                    ->default(Seccion::SISTEMA_ACUMULADO)
                    ->required(),
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
