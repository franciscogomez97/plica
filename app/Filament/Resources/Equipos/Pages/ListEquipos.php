<?php

namespace App\Filament\Resources\Equipos\Pages;

use App\Filament\Concerns\PestanasDeSeccion;
use App\Filament\Resources\Equipos\EquipoResource;
use App\Models\Seccion;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ListEquipos extends ListRecords
{
    use PestanasDeSeccion;

    protected static string $resource = EquipoResource::class;

    /** @var Collection<int, Seccion>|null */
    protected ?Collection $seccionesDelClub = null;

    /** Pestañas solo de las secciones por equipos. */
    protected function seccionesDelClub(): Collection
    {
        return $this->seccionesDelClub ??= EquipoResource::seccionesPorEquipos();
    }

    protected function consultaDeSeccion(Builder $query, Seccion $seccion): Builder
    {
        return $query->where('seccion_id', $seccion->id);
    }

    protected function etiquetaDeTodas(): string
    {
        return 'Todos';
    }

    public function getSubheading(): ?string
    {
        $temporada = auth()->user()->club?->temporadaActiva();

        return $temporada ? $temporada->nombre.' · los equipos son fijos toda la temporada' : 'Sin temporada activa';
    }

    protected function getHeaderActions(): array
    {
        return [
            // Como los socios: la lista de barcos la tienen en un papel o en el WhatsApp. Se pega y ya.
            Action::make('varios')
                ->label('Añadir varios')
                ->icon(Heroicon::OutlinedUserGroup)
                ->modalHeading('Añadir varios equipos de golpe')
                ->modalDescription('Un equipo por línea, con sus socios separados por «/». Si quieres ponerle nombre, delante y con dos puntos. Los socios que no existan se dan de alta.')
                ->modalSubmitActionLabel('Añadir')
                ->schema([
                    Select::make('seccion_id')
                        ->label('Sección')
                        ->options(fn (): array => $this->seccionesDelClub()->pluck('nombre', 'id')->all())
                        ->default(fn (): ?int => $this->seccionPorDefecto()?->id)
                        ->required(),
                    Textarea::make('lista')
                        ->label('Un equipo por línea')
                        ->placeholder("Mario López / Javier Ruiz\nLos Lucios: Ana Martín / Pedro Sánchez\nLuis García / Carlos Molina / Sergio Ramos")
                        ->rows(10)
                        ->autofocus()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $club = auth()->user()->club;
                    $seccion = $this->seccionesDelClub()->firstWhere('id', (int) $data['seccion_id']);
                    $temporada = $club->temporadaActiva();

                    if ($seccion === null || $temporada === null) {
                        Notification::make()->title('Hace falta una temporada activa')->danger()->send();

                        return;
                    }

                    $resultado = $club->altaDeEquipos($data['lista'], $seccion, $temporada);
                    $creados = count($resultado['creados']);

                    $notificacion = Notification::make()
                        ->title(match ($creados) {
                            0 => 'Ningún equipo nuevo',
                            1 => '1 equipo añadido',
                            default => "{$creados} equipos añadidos",
                        })
                        ->success();

                    $cuerpo = [];
                    if ($resultado['sociosNuevos'] !== []) {
                        $cuerpo[] = 'Socios dados de alta: '.implode(', ', $resultado['sociosNuevos']).'.';
                    }
                    if ($resultado['errores'] !== []) {
                        $notificacion->warning();
                        $cuerpo[] = 'No se han creado: '.implode('; ', $resultado['errores']).'.';
                    }
                    if ($cuerpo !== []) {
                        $notificacion->body(implode(' ', $cuerpo));
                    }

                    $notificacion->send();
                }),
            CreateAction::make(),
        ];
    }
}
