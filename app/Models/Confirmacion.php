<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * «Asistiré»: un socio dice que irá a una manga. Es solo una intención para
 * que el admin sepa con quién contar; la asistencia real la marca el admin
 * al pasar lista (Participacion), y solo esa puntúa.
 */
class Confirmacion extends Model
{
    protected $fillable = ['manga_id', 'socio_id'];

    public function manga(): BelongsTo
    {
        return $this->belongsTo(Manga::class);
    }

    public function socio(): BelongsTo
    {
        return $this->belongsTo(Socio::class);
    }
}
