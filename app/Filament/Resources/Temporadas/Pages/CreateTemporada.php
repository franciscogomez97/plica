<?php

namespace App\Filament\Resources\Temporadas\Pages;

use App\Filament\Resources\Temporadas\TemporadaResource;
use App\Models\Temporada;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

/**
 * Temporada nueva (normalmente desde el aviso de diciembre del Inicio). Los
 * socios y las secciones son del club y siguen ahí; los equipos se copian de
 * la temporada anterior para que el cambio de año sean dos toques: crear la
 * temporada y repasar los barcos.
 */
class CreateTemporada extends CreateRecord
{
    protected static bool $canCreateAnother = false;

    protected static string $resource = TemporadaResource::class;

    /** @var array{equipos: int, incompletos: int}|null */
    private ?array $copiados = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['club_id'] = auth()->user()->club_id;

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var Temporada $nueva */
        $nueva = $this->getRecord();
        $anterior = Temporada::query()
            ->where('club_id', $nueva->club_id)
            ->whereKeyNot($nueva->getKey())
            ->orderByDesc('id')
            ->first();

        if ($anterior !== null) {
            $this->copiados = $nueva->copiarEquiposDe($anterior);
            $this->anterior = $anterior->nombre;
        }
    }

    private ?string $anterior = null;

    protected function getCreatedNotification(): ?Notification
    {
        $notificacion = Notification::make()->title('Temporada creada')->success();

        if (($this->copiados['equipos'] ?? 0) > 0) {
            $n = $this->copiados['equipos'];
            $cuerpo = ($n === 1 ? '1 equipo copiado' : "{$n} equipos copiados")." de {$this->anterior}; revísalos en «Equipos».";
            if ($this->copiados['incompletos'] > 0) {
                $cuerpo .= ' '.($this->copiados['incompletos'] === 1 ? 'Uno se ha quedado' : $this->copiados['incompletos'].' se han quedado').' con menos gente por socios dados de baja.';
            }
            $notificacion->body($cuerpo);
        }

        return $notificacion;
    }
}
