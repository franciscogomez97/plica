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
        $esPuestos = fn (Get $get): bool => $get('sistema_puntuacion') === Seccion::SISTEMA_PUESTOS;

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
                // Dos formas de hacer el ranking de temporada: sumar lo pescado (con puntos
                // por participar si se quiere) o sumar puestos, el sistema de federación.
                Radio::make('sistema_puntuacion')
                    ->label('El ranking de la temporada')
                    ->options([
                        Seccion::SISTEMA_ACUMULADO => 'Suma lo pescado',
                        Seccion::SISTEMA_PUESTOS => 'Suma los puestos',
                    ])
                    ->descriptions([
                        Seccion::SISTEMA_ACUMULADO => 'Gana quien más suma en el año (gramos, centímetros o piezas), más los puntos por participar si los hay.',
                        Seccion::SISTEMA_PUESTOS => 'Cada manga da tantos puntos como tu puesto (1º = 1): gana quien menos suma. El sistema de federación.',
                    ])
                    ->default(Seccion::SISTEMA_ACUMULADO)
                    ->live()
                    ->required(),
                Radio::make('puestos_empate')
                    ->label('Si empatan en una manga, los puestos…')
                    ->options(Seccion::EMPATES)
                    ->default(Seccion::EMPATE_COMPARTIDO)
                    ->visible($esPuestos)
                    ->live()
                    ->required(),
                TextInput::make('puntos_participacion')
                    ->label('Puntos por participar')
                    ->helperText('Se suman por manga pescada. 0 = no se usan.')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->hidden($esPuestos)
                    ->live(onBlur: true),
                // Algunos clubes dan puntos también a quien no va (o se los quitan). Por
                // puestos es lo que se lleva un ausente (p. ej. socios + 1).
                TextInput::make('puntos_no_asistencia')
                    ->label(fn (Get $get): string => $esPuestos($get) ? 'Puntos de un ausente por manga' : 'Puntos por no ir')
                    ->helperText(fn (Get $get): string => $esPuestos($get)
                        ? '0 = el último de esa manga + 1. Muchos clubes ponen el número de socios + 1 (48 con 47 socios).'
                        : 'Por cada manga a la que un socio no va. 0 = nada. En negativo, resta.')
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
                        (string) ($get('sistema_puntuacion') ?: Seccion::SISTEMA_ACUMULADO),
                        $get('desempate') ?: null,
                        (int) ($get('puntos_no_asistencia') ?: 0),
                        (string) ($get('puestos_empate') ?: Seccion::EMPATE_COMPARTIDO),
                    ))
                    ->columnSpanFull(),
            ]);
    }
}
