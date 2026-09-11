<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Participacion extends Model
{
    protected $fillable = ['manga_id', 'socio_id', 'seccion_id', 'plica', 'pieza_mayor_gramos'];

    protected function casts(): array
    {
        return ['plica' => 'boolean'];
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
