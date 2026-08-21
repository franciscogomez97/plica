<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Manga extends Model
{
    public const ESTADO_PROGRAMADA = 'programada';
    public const ESTADO_CELEBRADA = 'celebrada';

    protected $fillable = ['temporada_id', 'seccion_id', 'nombre', 'fecha', 'lugar', 'estado', 'notas'];

    protected $attributes = ['estado' => self::ESTADO_PROGRAMADA];

    protected function casts(): array
    {
        return ['fecha' => 'date'];
    }

    public function temporada(): BelongsTo
    {
        return $this->belongsTo(Temporada::class);
    }

    /** Sección de la manga; null = jornada de todo el club. */
    public function seccion(): BelongsTo
    {
        return $this->belongsTo(Seccion::class);
    }

    public function participacions(): HasMany
    {
        return $this->hasMany(Participacion::class);
    }

    /**
     * Sincroniza la asistencia con una lista de socios marcados.
     * Crea participaciones para los nuevos y elimina las desmarcadas
     * SOLO si no tienen capturas (las que tienen datos se bloquean).
     *
     * @param  array<int|string>  $socioIds
     * @return array{creadas: int, eliminadas: int, bloqueadas: array<string>}
     */
    public function sincronizarAsistencia(array $socioIds, ?int $seccionId = null): array
    {
        // Una manga de sección apunta a los suyos: manda sobre lo que llegue.
        $seccionId = $this->seccion_id ?? $seccionId;
        $socioIds = array_map('intval', $socioIds);
        $actuales = $this->participacions()->with(['socio', 'capturas'])->get();

        $creadas = 0;
        $eliminadas = 0;
        $bloqueadas = [];

        foreach ($socioIds as $socioId) {
            if (! $actuales->contains('socio_id', $socioId)) {
                $this->participacions()->create([
                    'socio_id' => $socioId,
                    'seccion_id' => $seccionId,
                ]);
                $creadas++;
            }
        }

        foreach ($actuales as $participacion) {
            if (in_array($participacion->socio_id, $socioIds, true)) {
                continue;
            }

            if ($participacion->capturas->isNotEmpty()) {
                $bloqueadas[] = $participacion->socio->nombre;

                continue;
            }

            $participacion->delete();
            $eliminadas++;
        }

        return compact('creadas', 'eliminadas', 'bloqueadas');
    }

    /** Fecha ya pasada (o de hoy) y sigue sin marcarse como celebrada. */
    public function pendienteDeGestion(): bool
    {
        return $this->estado === self::ESTADO_PROGRAMADA
            && $this->fecha->lte(today());
    }

    /** Mangas por gestionar del club del usuario autenticado. */
    public static function pendientesDeGestion(): \Illuminate\Database\Eloquent\Builder
    {
        return static::query()
            ->where('estado', self::ESTADO_PROGRAMADA)
            ->whereDate('fecha', '<=', today())
            ->whereHas('temporada', fn ($q) => $q->where('club_id', auth()->user()?->club_id));
    }
}
