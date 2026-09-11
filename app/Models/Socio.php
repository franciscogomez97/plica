<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Socio extends Model
{
    protected $fillable = ['club_id', 'nombre', 'email', 'user_id', 'invite_token', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function participacions(): HasMany
    {
        return $this->hasMany(Participacion::class);
    }

    public function confirmacions(): HasMany
    {
        return $this->hasMany(Confirmacion::class);
    }

    /**
     * Link de acceso para enviar por WhatsApp. Un solo uso:
     * sin cuenta → crea la cuenta; con cuenta → restablece la contraseña.
     * Se invalida al usarse; el admin puede generar otro cuando haga falta.
     */
    public function accessUrl(): string
    {
        if (! $this->invite_token) {
            $this->update(['invite_token' => Str::random(48)]);
        }

        return route('acceso.show', $this->invite_token);
    }

    /**
     * El mensaje de WhatsApp con el enlace de acceso, el mismo en la ficha del
     * socio y en «Dar acceso»: personal y de un solo uso, nunca para un grupo.
     */
    public function mensajeAcceso(): string
    {
        $url = $this->accessUrl();
        $club = $this->club->nombre;

        return $this->user_id
            ? "Hola {$this->nombre} 👋 Este es tu acceso a Plica, la app de {$club}: {$url}\nAl abrirlo eliges una contraseña nueva. Es solo para ti y de un solo uso."
            : "Hola {$this->nombre} 👋 Este es tu acceso a Plica, la app de {$club}: {$url}\nAl abrirlo creas tu cuenta y ves los rankings, las clasificaciones y las próximas mangas. Es solo para ti y de un solo uso.";
    }
}
