<?php

namespace App\Filament\Resources\Seccions\Schemas;

use App\Models\Seccion;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

/**
 * La ficha de una sección, en tres bloques y en el orden en que se piensa:
 * qué es, cómo se hace el ranking de la temporada (sistema, asistencia o
 * ausencia, descartes y una sola regla de empates) y el resumen en una frase.
 */
class SeccionForm
{
    public static function configure(Schema $schema): Schema
    {
        $esPuestos = fn (Get $get): bool => $get('sistema_puntuacion') === Seccion::SISTEMA_PUESTOS;
        $esAcumulado = fn (Get $get): bool => ! $esPuestos($get);

        // Si el desempate elegido deja de tener sentido (cambia el criterio o el sistema), vuelve al de siempre.
        $corregirDesempate = function (Get $get, Set $set): void {
            $opciones = Seccion::desempatesPara((string) ($get('criterio') ?: Seccion::CRITERIO_PESO), (string) ($get('sistema_puntuacion') ?: Seccion::SISTEMA_ACUMULADO));

            if (! array_key_exists((string) $get('desempate'), $opciones)) {
                $set('desempate', Seccion::desempatePorDefecto((string) ($get('criterio') ?: Seccion::CRITERIO_PESO)));
            }

            if (! array_key_exists((string) $get('desempate_general'), Seccion::desempatesGeneralPara((string) ($get('criterio') ?: Seccion::CRITERIO_PESO)))) {
                $set('desempate_general', Seccion::DESEMPATE_COMPARTIDO);
            }
        };

        return $schema
            // Una sola columna: los bloques van en orden (la sección, el ranking, los socios, cómo puntúa) y a
            // dos columnas el bloque corto dejaba un hueco enorme debajo, junto al bloque largo del ranking.
            ->columns(1)
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
                                Seccion::CRITERIO_PESO => 'Gana quien más kilos saca.',
                                Seccion::CRITERIO_MEDIDA => 'Se apunta cada pez en centímetros. Gana quien más centímetros suma.',
                                Seccion::CRITERIO_PIEZAS => 'Gana quien más piezas saca.',
                            ])
                            ->default(Seccion::CRITERIO_PESO)
                            ->live()
                            ->afterStateUpdated($corregirDesempate)
                            ->required(),
                        // Quién pesca: en embarcación o carpfishing, la plica es del equipo.
                        Radio::make('modalidad')
                            ->label('¿Quién pesca?')
                            ->options([
                                Seccion::MODALIDAD_INDIVIDUAL => 'Cada socio por su cuenta',
                                Seccion::MODALIDAD_EQUIPOS => 'Por equipos (barcos, parejas…)',
                            ])
                            ->descriptions([
                                Seccion::MODALIDAD_INDIVIDUAL => 'Cada socio entrega su plica.',
                                Seccion::MODALIDAD_EQUIPOS => 'La plica es del equipo, fijo toda la temporada. Los equipos se forman en «Equipos».',
                            ])
                            ->default(Seccion::MODALIDAD_INDIVIDUAL)
                            // Con pesajes en la temporada activa no se cambia: escondería ese historial.
                            ->disabled(fn (?Seccion $record): bool => $record?->tieneHistorialEnTemporadaActiva() ?? false)
                            ->dehydrated(fn (?Seccion $record): bool => ! ($record?->tieneHistorialEnTemporadaActiva() ?? false))
                            ->helperText(fn (?Seccion $record): ?string => ($record?->tieneHistorialEnTemporadaActiva() ?? false)
                                ? 'Esta sección ya tiene pesajes esta temporada: quién pesca no se puede cambiar hasta la temporada que viene.'
                                : null)
                            ->live()
                            ->required(),
                        TextInput::make('tamano_equipo')
                            ->label('Personas por equipo')
                            ->helperText('2 en embarcación. En carpfishing, las que sean.')
                            ->numeric()
                            ->integer()
                            ->minValue(2)
                            ->maxValue(20)
                            ->default(2)
                            ->visible(fn (Get $get): bool => $get('modalidad') === Seccion::MODALIDAD_EQUIPOS)
                            ->required(fn (Get $get): bool => $get('modalidad') === Seccion::MODALIDAD_EQUIPOS)
                            ->live(onBlur: true),
                        // Solo informativo: no entra en ningún cálculo.
                        TextInput::make('numero_socios')
                            ->label('Número de socios de la sección')
                            ->helperText('Solo informativo. No entra en ningún cálculo.')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->nullable(),
                    ]),

                Section::make('El ranking de la temporada')
                    ->description('Cómo se suman las mangas del año en la clasificación general.')
                    ->schema([
                        Radio::make('sistema_puntuacion')
                            ->label('Sistema')
                            ->options([
                                Seccion::SISTEMA_ACUMULADO => 'Suma lo pescado',
                                Seccion::SISTEMA_PUESTOS => 'Suma los puestos (Sistema de la Federación)',
                            ])
                            ->descriptions([
                                Seccion::SISTEMA_ACUMULADO => 'Se suma lo pescado en todas las mangas, más los puntos por asistencia si los hay. Gana quien más suma.',
                                Seccion::SISTEMA_PUESTOS => 'Cada manga da tantos puntos como el puesto: 1º, 1 punto. Gana quien menos suma.',
                            ])
                            ->default(Seccion::SISTEMA_ACUMULADO)
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state) use ($corregirDesempate): void {
                                $corregirDesempate($get, $set);
                                $set('descartes_ausencias', $state === Seccion::SISTEMA_PUESTOS);
                            })
                            ->required(),

                        // Suma lo pescado: se puede premiar ir a las mangas.
                        TextInput::make('puntos_participacion')
                            ->label('Puntos por asistencia')
                            ->helperText('Por cada manga a la que se va, se pesque o no. 0 = no se usan.')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->visible($esAcumulado)
                            ->live(onBlur: true),

                        // Puntos por ausencia. Suma lo pescado: lo que suma (o resta, en negativo) cada
                        // manga a la que no se va. Por puestos: lo que se lleva quien no va.
                        TextInput::make('puntos_no_asistencia')
                            ->label('Puntos por ausencia')
                            // Sumando lo pescado, no ir nunca suma: 0 o castigo (en negativo).
                            ->maxValue(fn (Get $get): ?int => $esPuestos($get) ? null : 0)
                            ->validationMessages(['max' => 'Sumando lo pescado, no ir no puede sumar puntos: 0 o un número negativo.'])
                            ->helperText(fn (Get $get): string => $esPuestos($get)
                                ? 'Puntos que se lleva quien no va a una manga. Con 0, el último puesto de esa manga más uno. Con un número fijo, faltar cuesta siempre lo mismo.'
                                : 'Por cada manga a la que no se va. En negativo, resta. 0 = no se usan.')
                            ->numeric()
                            ->integer()
                            ->minValue(fn (Get $get): ?int => $esPuestos($get) ? 0 : null)
                            ->default(0)
                            ->live(onBlur: true),

                        // Por puestos: el bolo (ir y no pescar). C = los que pescaron, N = los que fueron.
                        Radio::make('bolo')
                            ->label('Quien va y no pesca (bolo) se lleva')
                            ->options(Seccion::BOLOS)
                            ->descriptions([
                                Seccion::BOLO_MEDIA => 'La fórmula de la federación: (pescaron + 1 + fueron) / 2. Pescaron 21 y fueron 29: 25,5 puntos.',
                                Seccion::BOLO_PRIMER_LIBRE => 'El puesto siguiente al último que pescó. Pescaron 21: 22 puntos.',
                                Seccion::BOLO_ULTIMO => 'Tantos puntos como participantes. Fueron 29: 29 puntos.',
                                Seccion::BOLO_FIJO => 'Un número fijo de puntos, el de la casilla de abajo.',
                                Seccion::BOLO_AUSENCIA => 'Los mismos puntos que no ir.',
                            ])
                            ->default(Seccion::BOLO_MEDIA)
                            ->visible($esPuestos)
                            ->live()
                            ->required(),
                        TextInput::make('puntos_bolo')
                            ->label('Puntos del bolo')
                            ->helperText('Los puntos de cada bolo. Normalmente, menos que la ausencia.')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->default(0)
                            ->visible(fn (Get $get): bool => $esPuestos($get) && $get('bolo') === Seccion::BOLO_FIJO)
                            ->required(fn (Get $get): bool => $esPuestos($get) && $get('bolo') === Seccion::BOLO_FIJO)
                            ->live(onBlur: true),

                        TextInput::make('descartes')
                            ->label('Descartes')
                            ->helperText('Peores mangas de cada socio que no cuentan en la clasificación general. 0 = cuentan todas.')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->live(onBlur: true),
                        // Con descartes, qué es «la peor manga» para quien faltó a alguna.
                        Radio::make('descartes_ausencias')
                            ->label('Qué mangas se descartan')
                            ->boolean(
                                'Todas: no ir cuenta como la peor manga y se descarta primero.',
                                'Solo las mangas a las que se fue.',
                            )
                            ->default(false)
                            ->visible(fn (Get $get): bool => (int) ($get('descartes') ?: 0) > 0)
                            ->live()
                            ->required(),

                        // Una sola regla para los empates, en las mangas y en el ranking: o
                        // decide algo (pieza mayor, piezas) o no decide nada y comparten.
                        Radio::make('desempate')
                            // Sumando puestos, esta regla es la de cada manga; el año tiene la suya, debajo.
                            ->label(fn (Get $get): string => $esPuestos($get) ? 'Empate en una manga' : 'Empate')
                            ->options(fn (Get $get): array => Seccion::desempatesPara(
                                (string) ($get('criterio') ?: Seccion::CRITERIO_PESO),
                                (string) ($get('sistema_puntuacion') ?: Seccion::SISTEMA_ACUMULADO),
                            ))
                            ->default(Seccion::DESEMPATE_PIEZAS)
                            ->helperText(fn (Get $get): string => $esPuestos($get)
                                ? 'Decide el puesto de la manga y, con él, los puntos. Si siguen igual, comparten puesto: 1º, 1º, 3º.'
                                : 'Vale para cada manga y para la clasificación general. Si siguen igual, comparten puesto: 1º, 1º, 3º.')
                            ->live()
                            ->required(),
                        // El empate en el ranking del año, sumando puestos: los reglamentos lo resuelven aparte
                        // (más gramos o mejor manga en Castilla-La Mancha; pieza mayor o menos capturas en la FEPyC).
                        Radio::make('desempate_general')
                            ->label('Empate en la clasificación general')
                            ->options(fn (Get $get): array => Seccion::desempatesGeneralPara((string) ($get('criterio') ?: Seccion::CRITERIO_PESO)))
                            ->default(Seccion::DESEMPATE_COMPARTIDO)
                            ->helperText('A igual suma de puntos en el año. Si siguen igual, comparten puesto.')
                            ->visible($esPuestos)
                            ->live()
                            ->required($esPuestos),
                    ]),

                Section::make('Socios de la sección')
                    ->description('Se apuntan solos al pesar en una manga de esta sección. Márcalos aquí para que salgan en el ranking desde el principio.')
                    ->schema([
                        CheckboxList::make('socios')
                            ->hiddenLabel()
                            ->relationship(
                                name: 'socios',
                                titleAttribute: 'nombre',
                                modifyQueryUsing: fn (Builder $query) => $query->where('club_id', auth()->user()->club_id)->orderBy('nombre'),
                            )
                            ->bulkToggleable()
                            ->searchable()
                            ->columns(['default' => 1, 'sm' => 2, 'lg' => 3]),
                    ])
                    ->collapsible(),

                Section::make('Así puntúa esta sección')
                    ->description('La misma frase que ven los socios junto al ranking.')
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
                                (int) ($get('puntos_no_asistencia') ?: 0),
                                (bool) $get('descartes_ausencias'),
                                (string) ($get('bolo') ?: Seccion::BOLO_MEDIA),
                                (int) ($get('puntos_bolo') ?: 0),
                                (string) ($get('desempate_general') ?: Seccion::DESEMPATE_COMPARTIDO),
                                (string) ($get('modalidad') ?: Seccion::MODALIDAD_INDIVIDUAL),
                                (int) ($get('tamano_equipo') ?: 2),
                            )),
                    ]),
            ]);
    }
}
