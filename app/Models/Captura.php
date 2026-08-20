<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Captura extends Model
{
    protected $fillable = ['participacion_id', 'piezas', 'peso_gramos', 'medida_mm', 'nota'];

    public function participacion(): BelongsTo
    {
        return $this->belongsTo(Participacion::class);
    }
}
