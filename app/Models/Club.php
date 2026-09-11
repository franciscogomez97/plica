<?php

namespace App\Models;

use App\Services\LogoClub;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class Club extends Model
{
    protected $fillable = [
        'nombre', 'slug', 'localidad', 'descripcion',
        'email_contacto', 'telefono_contacto', 'perfil_publico', 'logo',
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

    /** URL pública del logotipo (WebP en el disco «public»), o null si no hay. */
    public function logoUrl(): ?string
    {
        return filled($this->logo) ? Storage::disk(LogoClub::DISCO)->url($this->logo) : null;
    }

    /**
     * La marca del club para la cabecera de los paneles: su logo y su nombre.
     * Si no ha subido logo, solo el nombre (y Filament pone «Plica» si no hay club).
     */
    public function marca(): Htmlable
    {
        $logo = $this->logoUrl();

        return new HtmlString(
            '<span style="display:inline-flex; align-items:center; gap:.6rem; height:100%">'
            .($logo ? '<img src="'.e($logo).'" alt="" style="height:100%; width:auto; max-width:3rem; object-fit:contain; border-radius:.5rem">' : '')
            .'<span style="font-weight:700; font-size:1.05rem; white-space:nowrap">'.e($this->nombre).'</span>'
            .'</span>'
        );
    }

    /**
     * Alta de socios en bloque desde una lista pegada: un socio por línea,
     * con email opcional detrás del nombre («Paco Jiménez, paco@gmail.com»).
     * Los clubes no tienen CSV: tienen la lista en el WhatsApp o en un papel.
     * Aguanta numeraciones («1. »), viñetas y espacios de más. Los nombres
     * que ya existen en el club (sin distinguir mayúsculas ni tildes) se saltan.
     *
     * @return array{creados: array<int, string>, repetidos: array<int, string>}
     */
    public function altaDeSocios(string $texto): array
    {
        $vistos = $this->socios()
            ->pluck('nombre')
            ->mapWithKeys(fn (string $nombre) => [static::claveNombre($nombre) => true])
            ->all();

        $creados = [];
        $repetidos = [];

        foreach (preg_split('/\R/u', $texto) ?: [] as $linea) {
            $linea = trim($linea);

            if ($linea === '') {
                continue;
            }

            // Email opcional al final, separado por coma, punto y coma, tabulador o espacio.
            $email = null;
            if (preg_match('/^(.*?)[\s,;]+([^\s,;]+@[^\s,;]+)\s*$/u', $linea, $m)
                && filter_var($m[2], FILTER_VALIDATE_EMAIL)) {
                $linea = $m[1];
                $email = mb_strtolower($m[2]);
            }

            // Fuera numeración y viñetas, y separadores que sobren.
            $nombre = preg_replace('/^\s*(?:\d+\s*[.)\-–:]\s*|\d+\s+|[-•*·]\s*)/u', '', $linea) ?? $linea;
            $nombre = trim(preg_replace('/\s+/u', ' ', $nombre) ?? $nombre, " \t,;");

            if ($nombre === '') {
                continue;
            }

            $clave = static::claveNombre($nombre);

            if (isset($vistos[$clave])) {
                $repetidos[] = $nombre;

                continue;
            }

            $vistos[$clave] = true;
            $this->socios()->create(['nombre' => $nombre, 'email' => $email]);
            $creados[] = $nombre;
        }

        return compact('creados', 'repetidos');
    }

    /** «José  Pérez» y «jose perez» son el mismo socio. */
    private static function claveNombre(string $nombre): string
    {
        return Str::lower(Str::ascii(preg_replace('/\s+/u', ' ', trim($nombre)) ?? $nombre));
    }
}
