<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Temporadas\TemporadaResource;
use App\Models\Temporada;
use Filament\Widgets\Widget;

/**
 * Aviso de cambio de temporada: en diciembre, o si la temporada activa es de
 * un año ya pasado, el Inicio propone crear la siguiente con el nombre ya
 * puesto. Desaparece en cuanto existe una temporada del año siguiente.
 */
class NuevaTemporadaWidget extends Widget
{
    protected string $view = 'filament.widgets.nueva-temporada';

    protected static ?int $sort = -15;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $activa = auth()->user()?->club?->temporadaActiva();

        if ($activa === null) {
            return false;
        }

        $siguiente = static::anioDe($activa) + 1;
        $esHora = now()->month === 12 || static::anioDe($activa) < now()->year;

        return $esHora && ! Temporada::query()
            ->where('club_id', $activa->club_id)
            ->where('nombre', 'like', "%{$siguiente}%")
            ->exists();
    }

    public function getTemporadaActiva(): Temporada
    {
        return auth()->user()->club->temporadaActiva();
    }

    public function getNombreSiguiente(): string
    {
        return 'Temporada '.(static::anioDe($this->getTemporadaActiva()) + 1);
    }

    public function getUrlCrear(): string
    {
        return TemporadaResource::getUrl('create', ['nombre' => $this->getNombreSiguiente()]);
    }

    /** El año de una temporada: el que lleva en el nombre («Temporada 2026») o, si no, el de su alta. */
    public static function anioDe(Temporada $temporada): int
    {
        return preg_match('/(20\d{2})/', $temporada->nombre, $m)
            ? (int) $m[1]
            : (int) $temporada->created_at->year;
    }
}
