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

    /** Genera (si no existe) y devuelve el link de invitación para WhatsApp. */
    public function inviteUrl(): string
    {
        if (! $this->invite_token) {
            $this->update(['invite_token' => Str::random(48)]);
        }

        return route('invitacion.show', $this->invite_token);
    }
}
