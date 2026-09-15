<?php

namespace App\Filament\Resources\Equipos\Schemas;

use App\Filament\Resources\Equipos\EquipoResource;
use App\Models\Equipo;
use App\Models\Seccion;
use App\Models\Socio;
use Closure;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

/** Un equipo: su sección, un nombre opcional y sus socios. */
class EquipoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Select::make('seccion_id')
                    ->label('Sección')
                    ->options(fn (): array => EquipoResource::seccionesPorEquipos()->pluck('nombre', 'id')->all())
                    ->default(fn (): ?int => EquipoResource::seccionesPorEquipos()->count() === 1 ? EquipoResource::seccionesPorEquipos()->first()->id : null)
                    ->required()
                    ->live()
                    ->helperText('Solo las secciones que van por equipos.'),
                TextInput::make('nombre')
                    ->label('Nombre del equipo')
                    ->placeholder('Opcional: «Los Lucios». Sin nombre, se enseñan los nombres de sus socios.')
                    ->maxLength(80)
                    ->nullable(),
                CheckboxList::make('socios')
                    ->label('Socios del equipo')
                    ->relationship(
                        name: 'socios',
                        titleAttribute: 'nombre',
                        modifyQueryUsing: fn (Builder $query) => $query->where('club_id', auth()->user()->club_id)->where('activo', true)->orderBy('nombre'),
                    )
                    ->searchable()
                    ->bulkToggleable(false)
                    ->columns(['default' => 1, 'sm' => 2, 'lg' => 3])
                    ->minItems(1)
                    ->helperText(fn (Get $get): string => ($tamano = Seccion::find($get('seccion_id'))?->tamano_equipo)
                        ? "Equipos de {$tamano}. Si hoy falta alguien, el equipo cuenta igual."
                        : 'Marca a los socios que forman el equipo.')
                    // Un socio solo puede estar en un equipo por sección y temporada.
                    ->rules([
                        fn (Get $get, ?Equipo $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                            $seccion = Seccion::find($get('seccion_id'));
                            $temporada = auth()->user()->club?->temporadaActiva();
                            if ($seccion === null || $temporada === null) {
                                return;
                            }
                            foreach (Socio::whereIn('id', (array) $value)->get() as $socio) {
                                $otro = Equipo::equipoDe($socio, $seccion, $temporada);
                                if ($otro !== null && $otro->id !== $record?->id) {
                                    $fail("{$socio->nombre} ya está en otro equipo de {$seccion->nombre}: «{$otro->etiqueta()}».");
                                }
                            }
                        },
                    ]),
            ]);
    }
}
