<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Club extends Model
{
    protected $fillable = [
        'nombre', 'slug', 'localidad', 'descripcion',
        'email_contacto', 'telefono_contacto', 'perfil_publico',
    ];

    protected function casts(): array
    {
        return ['perfil_publico' => 'boolean'];
    }

    public function socios(): HasMany
    {
        return $this->hasMany(Socio::class);
    }

    public function temporadas(): HasMany
    {
        return $this->hasMany(Temporada::class);
    }

    public function seccions(): HasMany
    {
        return $this->hasMany(Seccion::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function temporadaActiva(): ?Temporada
    {
        return $this->temporadas()->where('activa', true)->latest('id')->first();
    }
}
