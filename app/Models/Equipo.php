<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un equipo de una sección por equipos (un barco en embarcación, una pareja o
 * trío en carpfishing) en una temporada. Fijo todo el año. La plica de la
 * manga es del equipo. El nombre es opcional: sin nombre, el equipo se
 * presenta con los nombres de sus socios.
 */
class Equipo extends Model
{
    protected $fillable = ['seccion_id', 'temporada_id', 'nombre'];

    public function seccion(): BelongsTo
    {
        return $this->belongsTo(Seccion::class);
    }

    public function temporada(): BelongsTo
    {
        return $this->belongsTo(Temporada::class);
    }

    public function participacions(): HasMany
    {
        return $this->hasMany(Participacion::class);
    }

    public function socios(): BelongsToMany
    {
        return $this->belongsToMany(Socio::class)->withTimestamps()->orderBy('nombre');
    }

    /** ¿Ya ha pesado alguna manga? Entonces sus socios no se tocan: cambiaría quién ganó. */
    public function tieneCapturas(): bool
    {
        return $this->participacions()->whereHas('capturas')->exists();
    }

    /** Cómo se presenta: su nombre o, si no tiene, los nombres de sus socios. */
    public function etiqueta(): string
    {
        return filled($this->nombre) ? $this->nombre : $this->miembrosTexto();
    }

    /** «Mario López / Javier Ruiz» */
    public function miembrosTexto(string $separador = ' / '): string
    {
        return $this->socios->pluck('nombre')->implode($separador) ?: 'Sin socios';
    }

    /** Un socio solo puede estar en un equipo por sección y temporada. */
    public static function equipoDe(Socio $socio, Seccion $seccion, Temporada $temporada): ?self
    {
        return static::query()
            ->where('seccion_id', $seccion->id)
            ->where('temporada_id', $temporada->id)
            ->whereHas('socios', fn ($q) => $q->where('socios.id', $socio->id))
            ->first();
    }
}
