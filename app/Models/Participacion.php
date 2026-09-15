<?php

namespace App\Models;

use App\Support\Participante;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Participacion extends Model
{
    protected $fillable = ['manga_id', 'socio_id', 'equipo_id', 'seccion_id', 'plica', 'pieza_mayor_gramos'];

    private ?Participante $participanteCache = null;

    protected function casts(): array
    {
        return ['plica' => 'boolean'];
    }

    /** Quien pesa en una manga de una sección pasa a ser socio de esa sección (si no lo era); en equipos, todos sus socios. */
    protected static function booted(): void
    {
        // Una participación es de un socio o de un equipo: nunca de ninguno ni de los dos.
        static::saving(function (Participacion $participacion): void {
            if (($participacion->socio_id === null) === ($participacion->equipo_id === null)) {
                throw new \LogicException('Una participación tiene que ser de un socio o de un equipo (y solo de uno).');
            }
        });

        static::created(function (Participacion $participacion): void {
            if ($participacion->seccion_id !== null) {
                foreach ($participacion->participante()->socios as $socio) {
                    $socio->seccions()->syncWithoutDetaching([$participacion->seccion_id]);
                }
            }
        });
    }

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class);
    }

    /** Quien participa: el socio o, en secciones por equipos, el equipo. */
    public function participante(): Participante
    {
        return $this->participanteCache ??= Participante::de($this);
    }

    /** Id del participante (socio o equipo); dentro de una sección no se mezclan. */
    public function participanteId(): int
    {
        return (int) ($this->equipo_id ?? $this->socio_id);
    }

    public function manga(): BelongsTo
    {
        return $this->belongsTo(Manga::class);
    }

    public function socio(): BelongsTo
    {
        return $this->belongsTo(Socio::class);
    }

    public function seccion(): BelongsTo
    {
        return $this->belongsTo(Seccion::class);
    }

    public function capturas(): HasMany
    {
        return $this->hasMany(Captura::class);
    }

    /**
     * El pez más grande en gramos: lo apuntado en el pesaje o, si se apuntó
     * pez a pez (o solo hubo uno), el mayor de las capturas.
     */
    public function piezaMayorGramos(): int
    {
        return max(
            (int) $this->pieza_mayor_gramos,
            (int) $this->capturas->where('piezas', 1)->max('peso_gramos'),
        );
    }

    /** El pez más largo en milímetros (las secciones por medida van pez a pez). */
    public function piezaMayorMm(): int
    {
        return (int) $this->capturas->max('medida_mm');
    }

    public function pesoTotal(): int
    {
        return (int) $this->capturas->sum('peso_gramos');
    }

    public function piezasTotal(): int
    {
        return (int) $this->capturas->sum('piezas');
    }

    public function medidaTotal(): int
    {
        return (int) $this->capturas->sum('medida_mm');
    }
}
