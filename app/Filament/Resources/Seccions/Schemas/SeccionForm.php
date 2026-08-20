<?php

namespace App\Filament\Resources\Seccions\Schemas;

use App\Models\Seccion;
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
                    ->label('Nombre')
                    ->placeholder('Orilla black bass, pato lucio, embarcación…')
                    ->required(),
                Select::make('criterio')
                    ->label('Criterio de clasificación de manga')
                    ->options(Seccion::CRITERIOS)
                    ->default(Seccion::CRITERIO_PESO)
                    ->helperText('Cómo se ordena cada manga en esta sección: por peso, por medida (captura y suelta) o por nº de piezas.')
                    ->required(),
                Select::make('sistema_puntuacion')
                    ->label('Sistema de ranking de temporada')
                    ->options(Seccion::SISTEMAS)
                    ->default(Seccion::SISTEMA_ACUMULADO)
                    ->helperText('Acumulado: se suma el peso/medida/piezas de todas las mangas. Por puestos: cada manga da tantos puntos como tu puesto (1º = 1) y gana quien menos acumula; no participar cuenta como último + 1.')
                    ->required(),
                TextInput::make('puntos_participacion')
                    ->label('Puntos por participar en una manga')
                    ->helperText('Se suman al total de cada manga pescada en esta sección (sistema acumulado). Pon 0 si no se usan.')
                    ->numeric()
                    ->minValue(0)
                    ->default(0),
                TextInput::make('descartes')
                    ->label('Descartes')
                    ->helperText('Número de peores mangas que NO cuentan para el ranking de esta sección. 0 = cuentan todas.')
                    ->numeric()
                    ->minValue(0)
                    ->default(0),
            ]);
    }
}
