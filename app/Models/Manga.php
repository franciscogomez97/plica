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

    protected $fillable = [
        'temporada_id', 'seccion_id', 'nombre', 'fecha', 'hora_inicio', 'hora_fin', 'lugar', 'ubicacion_url',
        'quedada_lugar', 'quedada_hora', 'quedada_url', 'estado', 'notas',
    ];

    protected $attributes = ['estado' => self::ESTADO_PROGRAMADA];

    protected function casts(): array
    {
        return ['fecha' => 'date'];
    }

    /** «08:00» a partir de lo que guarde la base de datos («08:00:00»). */
    public static function horaCorta(?string $hora): ?string
    {
        return filled($hora) ? substr($hora, 0, 5) : null;
    }

    /** «de 08:00 a 14:00», «desde las 08:00» o «hasta las 14:00»; null sin horario. */
    public function horario(): ?string
    {
        $inicio = static::horaCorta($this->hora_inicio);
        $fin = static::horaCorta($this->hora_fin);

        return match (true) {
            $inicio !== null && $fin !== null => "de {$inicio} a {$fin}",
            $inicio !== null => "desde las {$inicio}",
            $fin !== null => "hasta las {$fin}",
            default => null,
        };
    }

    /** «08:00–14:00», para listados. */
    public function horarioCorto(): ?string
    {
        $inicio = static::horaCorta($this->hora_inicio);
        $fin = static::horaCorta($this->hora_fin);

        return match (true) {
            $inicio !== null && $fin !== null => "{$inicio}–{$fin}",
            $inicio !== null => $inicio,
            $fin !== null => "hasta {$fin}",
            default => null,
        };
    }

    /** «a las 07:00 en Bar Manolo»; null si no hay quedada previa. */
    public function quedada(): ?string
    {
        $hora = static::horaCorta($this->quedada_hora);
        $lugar = filled($this->quedada_lugar) ? trim($this->quedada_lugar) : null;

        return match (true) {
            $hora !== null && $lugar !== null => "a las {$hora} en {$lugar}",
            $hora !== null => "a las {$hora}",
            $lugar !== null => "en {$lugar}",
            default => null,
        };
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
        // Toda manga es de una sección: los apuntados compiten en ella. En una
        // sección por equipos, los ids son de equipos: quien participa es el barco.
        $seccionId = $this->seccion_id;
        $columna = $this->porEquipos() ? 'equipo_id' : 'socio_id';
        $socioIds = array_map('intval', $socioIds);
        $actuales = $this->participacions()->with(['socio', 'equipo.socios', 'capturas'])->get();

        $creadas = 0;
        $eliminadas = 0;
        $bloqueadas = [];

        foreach ($socioIds as $socioId) {
            if (! $actuales->contains($columna, $socioId)) {
                $this->participacions()->create([
                    $columna => $socioId,
                    'seccion_id' => $seccionId,
                ]);
                $creadas++;
            }
        }

        foreach ($actuales as $participacion) {
            if (in_array((int) $participacion->{$columna}, $socioIds, true)) {
                continue;
            }

            if ($participacion->capturas->isNotEmpty()) {
                $bloqueadas[] = $participacion->participante()->nombre;

                continue;
            }

            $participacion->delete();
            $eliminadas++;
        }

        return compact('creadas', 'eliminadas', 'bloqueadas');
    }

    /** Enlace público de la clasificación de esta manga: cualquiera con el enlace la ve. */
    /** En una manga de una sección por equipos, quien participa es el equipo. */
    public function porEquipos(): bool
    {
        return (bool) $this->seccion?->esPorEquipos();
    }

    /** Los equipos que pueden participar: los de la sección en la temporada de la manga, con sus socios. */
    public function equiposPosibles()
    {
        return $this->porEquipos()
            ? $this->seccion->equipos()->where('temporada_id', $this->temporada_id)->with('socios')->get()
            : collect();
    }

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
