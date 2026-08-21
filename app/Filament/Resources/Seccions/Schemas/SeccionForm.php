<?php

namespace App\Filament\Resources\Seccions\Schemas;

use App\Models\Seccion;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SeccionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nombre')
                    ->label('Nombre de la sección')
                    ->placeholder('Bass orilla, lucio pato, embarcación…')
                    ->required(),
                Select::make('criterio')
                    ->label('¿Qué se mide en cada manga?')
                    ->options([
                        Seccion::CRITERIO_PESO => 'El peso (kilos)',
                        Seccion::CRITERIO_MEDIDA => 'La medida (centímetros — captura y suelta)',
                        Seccion::CRITERIO_PIEZAS => 'El número de piezas',
                    ])
                    ->default(Seccion::CRITERIO_PESO)
                    ->required(),
                Radio::make('sistema_puntuacion')
                    ->label('¿Cómo se decide el campeón de la temporada?')
                    ->options([
                        Seccion::SISTEMA_ACUMULADO => 'Sumando las capturas de todo el año',
                        Seccion::SISTEMA_PUESTOS => 'Por los puestos de cada manga',
                    ])
                    ->descriptions([
                        Seccion::SISTEMA_ACUMULADO => 'Se va sumando lo pescado en cada manga. Ejemplo: Juan pesca 3 kg en la 1ª manga y 2 kg en la 2ª → lleva 5 kg. Al final del año, el que más lleve es el campeón.',
                        Seccion::SISTEMA_PUESTOS => 'Lo que importa es en qué posición quedas cada día: el 1º se apunta 1 punto, el 2º 2 puntos, el 3º 3… y al final del año gana el que MENOS puntos tenga. Ejemplo: Juan hace un 1º y un 3º → 4 puntos; Marta hace un 2º y un 1º → 3 puntos → Marta va ganando. El día que no vas, te apuntas los puntos del último + 1.',
                    ])
                    ->default(Seccion::SISTEMA_ACUMULADO)
                    ->required(),
                TextInput::make('puntos_participacion')
                    ->label('Puntos de regalo por participar')
                    ->helperText('Puntos extra solo por presentarse a la manga, se pesque o no. Ejemplo: con 500, cada manga pescada suma 500 al total del año. Si tu club no usa esto, déjalo a 0. (Solo cuenta en el sistema de sumar capturas.)')
                    ->numeric()
                    ->minValue(0)
                    ->default(0),
                TextInput::make('descartes')
                    ->label('Descartes')
                    ->helperText('Cuántas de tus PEORES mangas se borran del ranking a final de año. Ejemplo: con 8 mangas y 2 descartes, a cada pescador solo le cuentan sus 6 mejores. Pon 0 para que cuenten todas.')
                    ->numeric()
                    ->minValue(0)
                    ->default(0),
            ]);
    }
}
