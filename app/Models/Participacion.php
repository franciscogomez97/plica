<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Participacion extends Model
{
    protected $fillable = ['manga_id', 'socio_id', 'seccion_id', 'plica'];

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
