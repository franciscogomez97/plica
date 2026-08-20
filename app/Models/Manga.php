<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Manga extends Model
{
    public const ESTADO_PROGRAMADA = 'programada';
    public const ESTADO_CELEBRADA = 'celebrada';

    protected $fillable = ['temporada_id', 'nombre', 'fecha', 'lugar', 'estado', 'notas'];

    protected function casts(): array
    {
        return ['fecha' => 'date'];
    }

    public function temporada(): BelongsTo
    {
        return $this->belongsTo(Temporada::class);
    }

    public function participacions(): HasMany
    {
        return $this->hasMany(Participacion::class);
    }
}
