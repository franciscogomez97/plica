<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_SOCIO = 'socio';

    protected $fillable = ['name', 'email', 'password', 'club_id', 'role'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'password_cambiada_at' => 'datetime',
            'guia_completada_at' => 'datetime',
        ];
    }

    /** Cuando el usuario cambia su contraseña, el paso de la guía se tacha solo. */
    protected static function booted(): void
    {
        static::updating(function (User $user) {
            if ($user->isDirty('password')) {
                $user->password_cambiada_at = now();
            }
        });
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function socio(): HasOne
    {
        return $this->hasOne(Socio::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'admin' => $this->isAdmin(),
            'app' => $this->club_id !== null,
            default => false,
        };
    }
}
