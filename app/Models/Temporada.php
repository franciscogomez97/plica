<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Temporada extends Model
{
    protected $fillable = ['club_id', 'nombre', 'activa'];

    /** Solo puede haber una temporada activa por club. */
    protected static function booted(): void
    {
        static::saved(function (Temporada $temporada) {
            if ($temporada->activa) {
                static::where('club_id', $temporada->club_id)
                    ->whereKeyNot($temporada->getKey())
                    ->where('activa', true)
                    ->update(['activa' => false]);
            }
        });
    }

    protected function casts(): array
    {
        return ['activa' => 'boolean'];
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function mangas(): HasMany
    {
        return $this->hasMany(Manga::class);
    }

    public function equipos(): HasMany
    {
        return $this->hasMany(Equipo::class);
    }

    /**
     * Al empezar la temporada, los barcos son casi siempre los mismos: se copian
     * los equipos de la anterior (mismo nombre, mismos socios, sin los de baja) en
     * cada sección por equipos que aún no tenga equipos este año. Luego son
     * equipos normales de esta temporada: se editan, se deshacen o se crean otros.
     *
     * @return array{equipos: int, incompletos: int}  cuántos se copiaron y cuántos quedaron con menos gente
     */
    public function copiarEquiposDe(Temporada $anterior): array
    {
        $copiados = 0;
        $incompletos = 0;
        $conEquipos = $this->equipos()->pluck('seccion_id')->flip();

        foreach ($anterior->equipos()->with(['socios', 'seccion'])->get() as $equipo) {
            if (! $equipo->seccion->esPorEquipos() || $conEquipos->has($equipo->seccion_id)) {
                continue;
            }
            $nuevo = $this->equipos()->create(['seccion_id' => $equipo->seccion_id, 'nombre' => $equipo->nombre]);
            $activos = $equipo->socios->where('activo', true)->pluck('id')->all();
            $nuevo->socios()->attach($activos);
            $copiados++;
            if (count($activos) < $equipo->socios->count()) {
                $incompletos++;
            }
        }

        return ['equipos' => $copiados, 'incompletos' => $incompletos];
    }
}
