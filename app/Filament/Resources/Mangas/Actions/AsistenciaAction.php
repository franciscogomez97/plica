<?php

namespace App\Filament\Resources\Mangas\Actions;

use App\Models\Manga;
use App\Models\Socio;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * «Marcar asistencia»: un checklist con todos los socios activos. La usan
 * el pesaje rápido y la pestaña de participaciones con la misma lógica:
 * los marcados se apuntan; los desmarcados se quitan salvo que tengan capturas.
 */
class AsistenciaAction
{
    /** @param  Closure(): Manga  $manga */
    public static function make(Closure $manga, string $name = 'asistencia'): Action
    {
        return Action::make($name)
            ->label('Marcar asistencia')
            ->icon(Heroicon::OutlinedClipboardDocumentCheck)
            ->modalHeading('¿Quién ha participado en esta manga?')
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
                CheckboxList::make('socios')
                    ->label('Socios')
                    ->options(fn (): array => Socio::query()
                        ->where('club_id', auth()->user()->club_id)
                        ->where('activo', true)
                        ->orderBy('nombre')
                        ->pluck('nombre', 'id')
                        ->all())
                    // Ya apuntados; y si aún no hay nadie, los que dijeron «asistiré».
                    ->default(function () use ($manga): array {
                        $apuntados = $manga()->participacions()->pluck('socio_id');

                        return ($apuntados->isNotEmpty() ? $apuntados : $manga()->confirmacions()->pluck('socio_id'))
                            ->map(fn ($id) => (string) $id)
                            ->all();
                    })
                    ->descriptions(fn (): array => $manga()
                        ->confirmacions()
                        ->pluck('socio_id')
                        ->mapWithKeys(fn ($id) => [(string) $id => 'Confirmó que vendría'])
                        ->all())
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
