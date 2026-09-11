<?php

namespace App\Filament\Resources\Seccions\Schemas;

use App\Models\Seccion;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class SeccionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nombre')
                    ->label('Nombre')
                    ->placeholder('Bass orilla, lucio pato…')
                    ->required()
                    // Dos secciones con el mismo nombre en un club es siempre un error.
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('club_id', auth()->user()->club_id))
                    ->validationMessages(['unique' => 'Ya tienes una sección con ese nombre.']),
                Radio::make('criterio')
                    ->label('Se compite por')
                    ->options([
                        Seccion::CRITERIO_PESO => 'Peso',
                        Seccion::CRITERIO_MEDIDA => 'Medida',
                        Seccion::CRITERIO_PIEZAS => 'Nº de piezas',
                    ])
                    ->descriptions([
                        Seccion::CRITERIO_PESO => 'Lo habitual: gana quien más kilos saca.',
                        Seccion::CRITERIO_MEDIDA => 'Captura y suelta: se apunta cada pez en centímetros.',
                        Seccion::CRITERIO_PIEZAS => 'Gana quien más peces saca; el peso solo desempata.',
                    ])
                    ->default(Seccion::CRITERIO_PESO)
                    ->live()
                    // Al cambiar el criterio, el desempate vuelve al que tiene sentido para él.
                    ->afterStateUpdated(fn (Set $set, ?string $state) => $set('desempate', Seccion::desempatePorDefecto((string) $state)))
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
                    ->default(0)
                    ->live(onBlur: true),
                // Algunos clubes dan puntos también a quien no va (o se los quitan).
                TextInput::make('puntos_no_asistencia')
                    ->label('Puntos por no ir')
                    ->helperText('Por cada manga a la que un socio no va. 0 = nada (lo normal). En negativo, resta.')
                    ->numeric()
                    ->integer()
                    ->default(0)
                    ->live(onBlur: true),
                TextInput::make('descartes')
                    ->label('Descartes')
                    ->helperText('Peores mangas que no cuentan al año. 0 = cuentan todas.')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->live(onBlur: true),
                // Nunca se desempata por lo mismo en lo que se empata; si sigue
                // igual, se comparte el puesto. Nada de sorteos.
                Radio::make('desempate')
                    ->label('Si empatan, gana…')
                    ->options(fn (Get $get): array => Seccion::desempatesPara((string) ($get('criterio') ?: Seccion::CRITERIO_PESO)))
                    ->default(Seccion::DESEMPATE_PIEZAS)
                    ->helperText('Si siguen igual, comparten puesto (1º, 1º, 3º).')
                    ->live()
                    ->required(),
                // Las reglas, en una frase, mientras se configuran: lo mismo que
                // verán los socios en el ranking.
                Placeholder::make('resumen')
                    ->label('Así puntúa esta sección')
                    ->content(fn (Get $get): string => Seccion::resumenReglasDe(
                        (string) ($get('criterio') ?: Seccion::CRITERIO_PESO),
                        (int) ($get('puntos_participacion') ?: 0),
                        (int) ($get('descartes') ?: 0),
                        Seccion::SISTEMA_ACUMULADO,
                        $get('desempate') ?: null,
                        (int) ($get('puntos_no_asistencia') ?: 0),
                    ))
                    ->columnSpanFull(),
            ]);
    }
}
