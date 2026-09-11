<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Manga extends Model
{
    public const ESTADO_PROGRAMADA = 'programada';

    public const ESTADO_CELEBRADA = 'celebrada';

    protected $fillable = ['temporada_id', 'seccion_id', 'nombre', 'fecha', 'lugar', 'ubicacion_url', 'estado', 'notas'];

    protected $attributes = ['estado' => self::ESTADO_PROGRAMADA];

    protected function casts(): array
    {
        return ['fecha' => 'date'];
    }

    public function temporada(): BelongsTo
    {
        return $this->belongsTo(Temporada::class);
    }

    /** Sección de la manga: toda manga es de una sección (el club compite por secciones). */
    public function seccion(): BelongsTo
    {
        return $this->belongsTo(Seccion::class);
    }

    public function participacions(): HasMany
    {
        return $this->hasMany(Participacion::class);
    }

    /** Socios que han dicho «asistiré». Intención, no asistencia: no puntúa. */
    public function confirmacions(): HasMany
    {
        return $this->hasMany(Confirmacion::class);
    }

    public function confirmadoPor(?Socio $socio): bool
    {
        return $socio !== null && $this->confirmacions()->where('socio_id', $socio->id)->exists();
    }

    /** Marca o quita el «asistiré» del socio. Devuelve el estado final. */
    public function alternarConfirmacion(Socio $socio): bool
    {
        $existente = $this->confirmacions()->where('socio_id', $socio->id)->first();

        if ($existente !== null) {
            $existente->delete();

            return false;
        }

        $this->confirmacions()->create(['socio_id' => $socio->id]);

        return true;
    }

    /**
     * Sincroniza la asistencia con una lista de socios marcados.
     * Crea participaciones para los nuevos y elimina las desmarcadas
     * SOLO si no tienen capturas (las que tienen datos se bloquean).
     *
     * @param  array<int|string>  $socioIds
     * @return array{creadas: int, eliminadas: int, bloqueadas: array<string>}
     */
    public function sincronizarAsistencia(array $socioIds): array
    {
        // Toda manga es de una sección: los apuntados compiten en ella.
        $seccionId = $this->seccion_id;
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

    /** Enlace público de la clasificación de esta manga: cualquiera con el enlace la ve. */
    public function urlPublica(): string
    {
        return route('club.manga', ['club' => $this->temporada->club->slug, 'manga' => $this->id]);
    }

    /** Fecha ya pasada (o de hoy) y sigue sin marcarse como celebrada. */
    public function pendienteDeGestion(): bool
    {
        return $this->estado === self::ESTADO_PROGRAMADA
            && $this->fecha->lte(today());
    }

    /** Mangas por gestionar del club del usuario autenticado. */
    public static function pendientesDeGestion(): Builder
    {
        return static::query()
            ->where('estado', self::ESTADO_PROGRAMADA)
            ->whereDate('fecha', '<=', today())
            ->whereHas('temporada', fn ($q) => $q->where('club_id', auth()->user()?->club_id));
    }
}
