<?php

namespace App\Filament\Resources\Mangas\Actions;

use App\Models\Manga;
use App\Models\Socio;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * «Marcar asistencia»: un checklist con todos los socios activos. La usan
 * el pesaje rápido y la pestaña de participaciones con la misma lógica:
 * los marcados se apuntan; los desmarcados se quitan salvo que tengan capturas.
 */
class AsistenciaAction
{
    /**
     * Las casillas del checklist. Por equipos: los equipos de la sección en esta
     * temporada. Por socios: los de la sección de la manga y, siempre, quien ya
     * esté apuntado o dijo «asistiré» (sea de donde sea); los demás socios del
     * club solo si se piden, marcados como «otra sección».
     *
     * @return array<int, string> id => etiqueta
     */
    public static function opciones(Manga $manga, bool $otrasSecciones): array
    {
        if ($manga->porEquipos()) {
            return $manga->equiposPosibles()->mapWithKeys(fn ($e) => [$e->id => $e->etiqueta()])->all();
        }
        $deLaSeccion = $manga->seccion->socios()->pluck('socios.id');
        $siempre = $manga->participacions()->pluck('socio_id')->filter()
            ->concat($manga->confirmacions()->pluck('socio_id'));
        $todos = Socio::query()
            ->where('club_id', $manga->temporada->club_id)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();
        $otros = $todos->whereNotIn('id', $deLaSeccion);
        if (! $otrasSecciones) {
            $otros = $otros->whereIn('id', $siempre);
        }

        return $todos->whereIn('id', $deLaSeccion)->pluck('nombre', 'id')
            ->union($otros->mapWithKeys(fn (Socio $s) => [$s->id => "{$s->nombre} (otra sección)"]))
            ->all();
    }

    /** @param  Closure(): Manga  $manga */
    public static function make(Closure $manga, string $name = 'asistencia'): Action
    {
        return Action::make($name)
            ->label('Marcar asistencia')
            ->icon(Heroicon::OutlinedClipboardDocumentCheck)
            ->modalHeading(fn (): string => $manga()->porEquipos() ? '¿Qué equipos han participado en esta manga?' : '¿Quién ha participado en esta manga?')
            ->modalDescription(function () use ($manga): string {
                $confirmados = $manga()->confirmacions()->count();

                return 'Marca a los que han venido. Los desmarcados se quitan, salvo que ya tengan capturas.'
                    .($confirmados > 0
                        ? ' '.($confirmados === 1 ? '1 socio confirmó que vendría' : "{$confirmados} socios confirmaron que vendrían")
                            .'; si aún no habías pasado lista, ya vienen marcados. Tú decides quién ha venido de verdad.'
                        : '');
            })
            ->modalSubmitActionLabel('Guardar asistencia')
            ->schema([
                // Los de otras secciones no salen por defecto: un «Seleccionar todos» con prisa apuntaba
                // a los 40 del club a una manga de embarcación. Se enseñan a petición.
                Toggle::make('otras_secciones')
                    ->label('Mostrar también a los socios de otras secciones')
                    ->default(false)
                    ->dehydrated(false)
                    ->live()
                    ->visible(fn (): bool => ! $manga()->porEquipos()),
                CheckboxList::make('socios')
                    ->label(fn (): string => $manga()->porEquipos() ? 'Equipos' : 'Socios')
                    // Los de la sección de la manga (y quien ya esté apuntado, sea de donde sea); los de
                    // otras secciones solo con el interruptor, marcados como «otra sección».
                    // Por equipos: los equipos de la sección en esta temporada, y punto.
                    ->options(fn (Get $get): array => static::opciones($manga(), (bool) $get('otras_secciones')))
                    // Ya apuntados; y si aún no hay nadie, los que dijeron «asistiré».
                    ->default(function () use ($manga): array {
                        if ($manga()->porEquipos()) {
                            $apuntados = $manga()->participacions()->pluck('equipo_id')->filter();
                            // Sin nadie apuntado: los equipos con algún socio que dijo «asistiré».
                            $confirmados = $manga()->confirmacions()->pluck('socio_id');
                            $conConfirmado = $manga()->equiposPosibles()->filter(fn ($e) => $e->socios->pluck('id')->intersect($confirmados)->isNotEmpty())->pluck('id');

                            return ($apuntados->isNotEmpty() ? $apuntados : $conConfirmado)->map(fn ($id) => (string) $id)->all();
                        }
                        $apuntados = $manga()->participacions()->pluck('socio_id')->filter();

                        return ($apuntados->isNotEmpty() ? $apuntados : $manga()->confirmacions()->pluck('socio_id'))
                            ->map(fn ($id) => (string) $id)
                            ->all();
                    })
                    ->descriptions(function () use ($manga): array {
                        $confirmados = $manga()->confirmacions()->with('socio')->get();
                        if ($manga()->porEquipos()) {
                            return $manga()->equiposPosibles()
                                ->mapWithKeys(function ($e) use ($confirmados) {
                                    $nombres = $confirmados->whereIn('socio_id', $e->socios->pluck('id'))->map(fn ($c) => $c->socio->nombre);

                                    return $nombres->isEmpty() ? [] : [(string) $e->id => 'Confirmó que vendría: '.$nombres->implode(', ')];
                                })
                                ->all();
                        }

                        return $confirmados->pluck('socio_id')->mapWithKeys(fn ($id) => [(string) $id => 'Confirmó que vendría'])->all();
                    })
                    ->searchable()
                    ->columns(['default' => 1, 'sm' => 2])
                    ->bulkToggleable(),
            ])
            ->action(function (array $data) use ($manga): void {
                $resultado = $manga()->sincronizarAsistencia($data['socios'] ?? []);

                $notificacion = Notification::make()
                    ->title('Asistencia guardada')
                    ->body("{$resultado['creadas']} añadidos · {$resultado['eliminadas']} quitados")
                    ->success();

                if ($resultado['bloqueadas'] !== []) {
                    $notificacion
                        ->warning()
                        ->body('No se quitaron (tienen capturas): '.implode(', ', $resultado['bloqueadas']));
                }

                $notificacion->send();
            });
    }
}
