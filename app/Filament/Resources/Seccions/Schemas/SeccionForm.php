<?php

namespace App\Filament\Resources\Seccions\Schemas;

use App\Models\Seccion;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

/**
 * La ficha de una sección, en cuatro bloques y en el orden en que se piensa:
 * qué es, cómo se hace el ranking de la temporada, qué decide un empate y el
 * resumen en una frase. Cada sistema de ranking enseña solo lo suyo.
 */
class SeccionForm
{
    public static function configure(Schema $schema): Schema
    {
        $esPuestos = fn (Get $get): bool => $get('sistema_puntuacion') === Seccion::SISTEMA_PUESTOS;
        $esAcumulado = fn (Get $get): bool => ! $esPuestos($get);

        return $schema
            ->components([
                Section::make('La sección')
                    ->schema([
                        TextInput::make('nombre')
                            ->label('Nombre')
                            ->placeholder('Orilla, embarcación, lucio pato…')
                            ->required()
                            // Dos secciones con el mismo nombre en un club es siempre un error.
                            ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('club_id', auth()->user()->club_id))
                            ->validationMessages(['unique' => 'Ya tienes una sección con ese nombre.']),
                        Radio::make('criterio')
                            ->label('En cada manga se compite por')
                            ->options([
                                Seccion::CRITERIO_PESO => 'Peso',
                                Seccion::CRITERIO_MEDIDA => 'Medida',
                                Seccion::CRITERIO_PIEZAS => 'Nº de piezas',
                            ])
                            ->descriptions([
                                Seccion::CRITERIO_PESO => 'Lo habitual: gana la manga quien más kilos saca.',
                                Seccion::CRITERIO_MEDIDA => 'Captura y suelta: se apunta cada pez en centímetros y gana quien más suma.',
                                Seccion::CRITERIO_PIEZAS => 'Gana quien más peces saca; el peso solo desempata.',
                            ])
                            ->default(Seccion::CRITERIO_PESO)
                            ->live()
                            // Al cambiar el criterio, el desempate vuelve al que tiene sentido para él.
                            ->afterStateUpdated(fn (Set $set, ?string $state) => $set('desempate', Seccion::desempatePorDefecto((string) $state)))
                            ->required(),
                    ]),

                Section::make('El ranking de la temporada')
                    ->description('Cómo se juntan las mangas del año en una clasificación general.')
                    ->schema([
                        Radio::make('sistema_puntuacion')
                            ->label('Sistema')
                            ->options([
                                Seccion::SISTEMA_ACUMULADO => 'Suma lo pescado',
                                Seccion::SISTEMA_PUESTOS => 'Suma los puestos',
                            ])
                            ->descriptions([
                                Seccion::SISTEMA_ACUMULADO => 'Gana quien más suma en el año (kilos, centímetros o piezas), más los puntos por asistencia si los hay.',
                                Seccion::SISTEMA_PUESTOS => 'Cada manga da tantos puntos como tu puesto (1º = 1 punto) y gana quien menos suma. El sistema de federación.',
                            ])
                            ->default(Seccion::SISTEMA_ACUMULADO)
                            ->live()
                            ->required(),

                        // Suma lo pescado: se puede premiar ir a las mangas.
                        TextInput::make('puntos_participacion')
                            ->label('Puntos por asistencia')
                            ->helperText('Se suman por cada manga a la que se va, se pesque o no. 0 = no se usan.')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->visible($esAcumulado)
                            ->live(onBlur: true),

                        // Suma los puestos: qué pasa al empatar y qué cuesta no ir.
                        Radio::make('puestos_empate')
                            ->label('Si dos o más empatan en una manga')
                            ->options(Seccion::EMPATES)
                            ->default(Seccion::EMPATE_COMPARTIDO)
                            ->visible($esPuestos)
                            ->live()
                            ->required(),
                        TextInput::make('puntos_no_asistencia')
                            ->label('Puntos por no ir a una manga')
                            ->helperText('Lo que se lleva quien no va. Lo habitual: el número de socios + 1 (48 con 47 socios). A 0, el último de esa manga + 1.')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->default(0)
                            ->visible($esPuestos)
                            ->live(onBlur: true),

                        TextInput::make('descartes')
                            ->label('Descartes')
                            ->helperText('Peores mangas de cada socio que no cuentan al final del año. 0 = cuentan todas.')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->live(onBlur: true),
                    ]),

                Section::make('Empates')
                    ->schema([
                        // Nunca se desempata por lo mismo en lo que se empata; si sigue
                        // igual, se comparte el puesto. Nada de sorteos.
                        Radio::make('desempate')
                            ->label('Si empatan, gana…')
                            ->options(fn (Get $get): array => Seccion::desempatesPara((string) ($get('criterio') ?: Seccion::CRITERIO_PESO)))
                            ->default(Seccion::DESEMPATE_PIEZAS)
                            ->helperText('Vale para las mangas y para el ranking. Si siguen igual, comparten puesto (1º, 1º, 3º).')
                            ->live()
                            ->required(),
                    ]),

                Section::make('Así puntúa esta sección')
                    ->description('Lo mismo que verán los socios junto al ranking.')
                    ->schema([
                        // Las reglas, en una frase, mientras se configuran.
                        Placeholder::make('resumen')
                            ->hiddenLabel()
                            ->content(fn (Get $get): string => Seccion::resumenReglasDe(
                                (string) ($get('criterio') ?: Seccion::CRITERIO_PESO),
                                $esAcumulado($get) ? (int) ($get('puntos_participacion') ?: 0) : 0,
                                (int) ($get('descartes') ?: 0),
                                (string) ($get('sistema_puntuacion') ?: Seccion::SISTEMA_ACUMULADO),
                                $get('desempate') ?: null,
                                $esPuestos($get) ? (int) ($get('puntos_no_asistencia') ?: 0) : 0,
                                (string) ($get('puestos_empate') ?: Seccion::EMPATE_COMPARTIDO),
                            )),
                    ]),
            ]);
    }
}
