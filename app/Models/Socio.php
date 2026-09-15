<?php

namespace App\Models;

use App\Services\FotoSocio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Socio extends Model
{
    protected $fillable = ['club_id', 'nombre', 'email', 'telefono', 'foto', 'licencia', 'user_id', 'invite_token', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    protected static function booted(): void
    {
        // La foto vieja no se queda huérfana: ni al cambiarla o quitarla, ni al borrar al socio.
        static::updated(function (Socio $socio): void {
            if ($socio->wasChanged('foto')) {
                FotoSocio::borrar($socio->getOriginal('foto'));
            }
        });
        static::deleting(function (Socio $socio): void {
            // Con historial (pesajes propios o de un equipo suyo) no se borra: se da de baja.
            if ($socio->tieneHistorial()) {
                throw new \LogicException("{$socio->nombre} tiene historial en el club: no se puede borrar, solo dar de baja.");
            }
            FotoSocio::borrar($socio->foto);
        });
    }

    /**
     * ¿Sale en alguna clasificación? Por sus pesajes o por los de un equipo del que
     * forma parte (en equipos, la plica es del barco, no del socio). Borrarlo
     * cambiaría quién ganó: solo se da de baja.
     */
    public function tieneHistorial(): bool
    {
        return $this->participacions()->exists()
            || $this->equipos()->whereHas('participacions')->exists();
    }

    /** URL de su foto (WebP cuadrada en el disco «public»), o null si no tiene. */
    public function fotoUrl(): ?string
    {
        return filled($this->foto) ? Storage::disk(FotoSocio::DISCO)->url($this->foto) : null;
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

    /**
     * Las secciones en las que compite. Se aprende sola al pesarle en una manga
     * (ver Participacion::booted) y se corrige a mano en su ficha o en la sección.
     */
    public function seccions(): BelongsToMany
    {
        return $this->belongsToMany(Seccion::class, 'seccion_socio')->withTimestamps();
    }

    public function equipos(): BelongsToMany
    {
        return $this->belongsToMany(Equipo::class)->withTimestamps();
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
     * El teléfono tal como lo teclea el club («600 11 22 33», «+34 600112233»,
     * «0034600112233») al formato de wa.me: solo dígitos con prefijo de país.
     * Un móvil español de 9 cifras lleva el 34 por defecto. Null si no es un teléfono.
     */
    public static function telefonoWhatsApp(?string $telefono): ?string
    {
        $digitos = preg_replace('/\D+/', '', (string) $telefono) ?? '';

        if (str_starts_with($digitos, '00')) {
            $digitos = substr($digitos, 2);
        }

        if (strlen($digitos) === 9 && preg_match('/^[6-9]/', $digitos)) {
            $digitos = '34'.$digitos;
        }

        return strlen($digitos) >= 10 && strlen($digitos) <= 15 ? $digitos : null;
    }

    public function numeroWhatsApp(): ?string
    {
        return static::telefonoWhatsApp($this->telefono);
    }

    /**
     * El botón de WhatsApp de «Dar acceso» y de la ficha: con teléfono, abre
     * directamente el chat del socio con el mensaje escrito; sin él, WhatsApp
     * pide elegir el contacto.
     */
    public function urlWhatsAppAcceso(): string
    {
        $numero = $this->numeroWhatsApp();

        return 'https://wa.me/'.($numero ?? '').'?text='.rawurlencode($this->mensajeAcceso());
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
