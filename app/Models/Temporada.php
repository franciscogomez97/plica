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
}
