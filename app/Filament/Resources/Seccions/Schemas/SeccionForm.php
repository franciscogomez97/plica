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
                                Seccion::CRITERIO_PESO => 'Lo habitual: gana la manga quien más kilos saca.',
                                Seccion::CRITERIO_MEDIDA => 'Captura y suelta: se apunta cada pez en centímetros y gana quien más suma.',
                                Seccion::CRITERIO_PIEZAS => 'Gana quien más peces saca; el peso solo desempata.',
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
                                Seccion::MODALIDAD_INDIVIDUAL => 'Lo normal en orilla: cada uno pesa lo suyo.',
                                Seccion::MODALIDAD_EQUIPOS => 'Embarcación o carpfishing: la plica es del equipo, fijo toda la temporada. Los equipos se forman en «Equipos».',
                            ])
                            ->default(Seccion::MODALIDAD_INDIVIDUAL)
                            ->live()
                            ->required(),
                        TextInput::make('tamano_equipo')
                            ->label('Personas por equipo')
                            ->helperText('2 en embarcación; en carpfishing, las que sean.')
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
                            ->helperText('Solo para tenerlo a la vista: no afecta a ningún cálculo. Sirve, por ejemplo, para poner los puntos por ausencia (socios + 1).')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->nullable(),
                    ]),

                Section::make('El ranking de la temporada')
                    ->description('Cómo se juntan las mangas del año en una clasificación general.')
                    ->schema([
                        Radio::make('sistema_puntuacion')
                            ->label('Sistema')
                            ->options([
                                Seccion::SISTEMA_ACUMULADO => 'Suma lo pescado',
                                Seccion::SISTEMA_PUESTOS => 'Suma los puestos (Sistema de la Federación)',
                            ])
                            ->descriptions([
                                Seccion::SISTEMA_ACUMULADO => 'Gana quien más suma en el año (kilos, centímetros o piezas), más los puntos por asistencia si los hay.',
                                Seccion::SISTEMA_PUESTOS => 'Cada manga da tantos puntos como tu puesto (1º = 1 punto) y gana quien menos suma.',
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
                            ->helperText('Se suman por cada manga a la que se va, se pesque o no. 0 = no se usan.')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->visible($esAcumulado)
                            ->live(onBlur: true),

                        // Puntos por ausencia. Suma lo pescado: lo que suma (o resta, en negativo) cada
                        // manga a la que no se va. Por puestos: lo que se lleva quien no va.
                        TextInput::make('puntos_no_asistencia')
                            ->label('Puntos por ausencia')
                            ->helperText(fn (Get $get): string => $esPuestos($get)
                                ? 'Los puntos que se lleva quien no va a una manga (aquí, cuantos más puntos, peor). Con 0, es automático: el último de esa manga más uno; si fueron 29, el ausente se lleva 30. Con un número fijo, faltar cuesta siempre lo mismo: lo habitual es el número de socios más uno, 48 con 47 socios.'
                                : 'Por cada manga a la que un socio no va. En negativo, resta (castigo). 0 = nada.')
                            ->numeric()
                            ->integer()
                            ->minValue(fn (Get $get): ?int => $esPuestos($get) ? 0 : null)
                            ->default(0)
                            ->live(onBlur: true),

                        // Por puestos: el bolo (ir y no pescar). C = los que pescaron, N = los que fueron.
                        Radio::make('bolo')
                            ->label('Quien va y no pesca (el «bolo») se lleva…')
                            ->options(Seccion::BOLOS)
                            ->descriptions([
                                Seccion::BOLO_MEDIA => 'La fórmula oficial: ((pescaron + 1) + fueron) / 2. Si pescaron 21 y fueron 29, cada bolo se lleva (22 + 29) / 2 = 25,5.',
                                Seccion::BOLO_PRIMER_LIBRE => 'El puesto siguiente al último que pescó. Si pescaron 21, cada bolo se lleva 22. Hacer bolo casi no se nota.',
                                Seccion::BOLO_ULTIMO => 'Tantos puntos como gente fue. Si fueron 29, cada bolo se lleva 29. Hacer bolo cuesta como quedar el último.',
                                Seccion::BOLO_FIJO => 'Siempre los mismos puntos, pase lo que pase en la manga. Pon el número debajo.',
                                Seccion::BOLO_AUSENCIA => 'Ir sin pescar cuesta lo mismo que no ir: los puntos por ausencia de aquí abajo.',
                            ])
                            ->default(Seccion::BOLO_MEDIA)
                            ->visible($esPuestos)
                            ->live()
                            ->required(),
                        TextInput::make('puntos_bolo')
                            ->label('Puntos del bolo')
                            ->helperText('Los que se lleva cada bolo, siempre. Lo normal es que sea menos que la ausencia, para que ir sin pescar sea mejor que no ir.')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->default(0)
                            ->visible(fn (Get $get): bool => $esPuestos($get) && $get('bolo') === Seccion::BOLO_FIJO)
                            ->required(fn (Get $get): bool => $esPuestos($get) && $get('bolo') === Seccion::BOLO_FIJO)
                            ->live(onBlur: true),

                        TextInput::make('descartes')
                            ->label('Descartes')
                            ->helperText('Peores mangas de cada socio que no cuentan al final del año. 0 = cuentan todas.')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->live(onBlur: true),
                        // Con descartes, qué es «la peor manga» para quien faltó a alguna.
                        Radio::make('descartes_ausencias')
                            ->label('¿Qué mangas se pueden descartar?')
                            ->boolean(
                                'También las no pescadas: faltar cuenta como la peor manga y es la primera que se descarta.',
                                'Solo las pescadas: se quita la peor de las que fue; las que se perdió cuentan igual.',
                            )
                            ->default(false)
                            ->visible(fn (Get $get): bool => (int) ($get('descartes') ?: 0) > 0)
                            ->live()
                            ->required(),

                        // Una sola regla para los empates, en las mangas y en el ranking: o
                        // decide algo (pieza mayor, piezas) o no decide nada y comparten.
                        Radio::make('desempate')
                            // Sumando puestos, esta regla es la de cada manga; el año tiene la suya, debajo.
                            ->label(fn (Get $get): string => $esPuestos($get) ? 'Si empatan en una manga, ¿quién gana?' : 'Si empatan, ¿quién gana?')
                            ->options(fn (Get $get): array => Seccion::desempatesPara(
                                (string) ($get('criterio') ?: Seccion::CRITERIO_PESO),
                                (string) ($get('sistema_puntuacion') ?: Seccion::SISTEMA_ACUMULADO),
                            ))
                            ->default(Seccion::DESEMPATE_PIEZAS)
                            ->helperText(fn (Get $get): string => $esPuestos($get)
                                ? 'Decide el puesto de cada manga, y con él los puntos. Si desempata algo y siguen igual, comparten puesto (1º, 1º, 3º).'
                                : 'Vale para cada manga y para el ranking. Si desempata algo y siguen igual, comparten puesto (1º, 1º, 3º).')
                            ->live()
                            ->required(),
                        // El empate en el ranking del año, sumando puestos: los reglamentos lo resuelven aparte
                        // (más gramos o mejor manga en Castilla-La Mancha; pieza mayor o menos capturas en la FEPyC).
                        Radio::make('desempate_general')
                            ->label('Si empatan en el ranking de la temporada, ¿quién gana?')
                            ->options(fn (Get $get): array => Seccion::desempatesGeneralPara((string) ($get('criterio') ?: Seccion::CRITERIO_PESO)))
                            ->default(Seccion::DESEMPATE_COMPARTIDO)
                            ->helperText('A igual suma de puntos en el año. Si desempata algo y siguen igual, comparten puesto.')
                            ->visible($esPuestos)
                            ->live()
                            ->required($esPuestos),
                    ]),

                Section::make('Socios de la sección')
                    ->description('Se marcan solos al pesarles en una manga de esta sección. Márcalos aquí para que salgan en el ranking desde el principio, aunque aún no hayan ido a ninguna manga.')
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
