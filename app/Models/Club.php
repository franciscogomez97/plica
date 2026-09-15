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
    /**
     * @param  Seccion|null  $seccion  Si se da, los socios nuevos entran también en esa sección.
     */
    public function altaDeSocios(string $texto, ?Seccion $seccion = null): array
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

            // Email y teléfono opcionales al final, en cualquier orden, separados por
            // coma, punto y coma, tabulador o espacio: «Paco Jiménez 600 11 22 33, paco@gmail.com».
            $email = null;
            $telefono = null;
            for ($i = 0; $i < 2; $i++) {
                if ($email === null
                    && preg_match('/^(.*?)[\s,;]+([^\s,;]+@[^\s,;]+)\s*$/u', $linea, $m)
                    && filter_var($m[2], FILTER_VALIDATE_EMAIL)) {
                    $linea = $m[1];
                    $email = mb_strtolower($m[2]);

                    continue;
                }

                if ($telefono === null
                    && preg_match('/^(.*?)[\s,;]+(\+?\d[\d\s.\-]{6,}\d)\s*$/u', $linea, $m)
                    && Socio::telefonoWhatsApp($m[2]) !== null) {
                    $linea = $m[1];
                    $telefono = trim(preg_replace('/\s+/u', ' ', $m[2]) ?? $m[2]);

                    continue;
                }

                break;
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
            $socio = $this->socios()->create(['nombre' => $nombre, 'email' => $email, 'telefono' => $telefono]);
            if ($seccion !== null) {
                $socio->seccions()->attach($seccion->id);
            }
            $creados[] = $nombre;
        }

        return compact('creados', 'repetidos');
    }

    /**
     * Alta de equipos pegando la lista: un equipo por línea, sus socios separados
     * por «/» (o «,» o «;»), y un nombre opcional delante con dos puntos:
     * «Los Lucios: Mario López / Javier Ruiz». Los socios que no existan se dan de
     * alta; todos quedan apuntados a la sección. Un socio solo puede estar en un
     * equipo por sección y temporada: la línea que lo repita se rechaza entera.
     *
     * @return array{creados: string[], errores: string[], sociosNuevos: string[]}
     */
    public function altaDeEquipos(string $texto, Seccion $seccion, Temporada $temporada): array
    {
        $socios = $this->socios()->get()->keyBy(fn (Socio $s) => static::claveNombre($s->nombre));
        $ocupados = Equipo::query()
            ->where('seccion_id', $seccion->id)
            ->where('temporada_id', $temporada->id)
            ->with('socios')->get()
            ->flatMap(fn (Equipo $e) => $e->socios->pluck('id'))
            ->flip()->all();

        $creados = [];
        $errores = [];
        $sociosNuevos = [];

        foreach (preg_split('/\R/u', $texto) ?: [] as $linea) {
            $linea = preg_replace('/^\s*(?:\d+\s*[.)\-–:]\s*|\d+\s+|[-•*·]\s*)/u', '', trim($linea)) ?? $linea;
            $linea = trim($linea);

            if ($linea === '') {
                continue;
            }

            $nombreEquipo = null;
            if (preg_match('/^([^:\/,;]+):\s*(.+)$/u', $linea, $m)) {
                $nombreEquipo = trim($m[1]);
                $linea = $m[2];
            }

            $nombres = array_values(array_filter(array_map(
                fn (string $n) => trim(preg_replace('/\s+/u', ' ', $n) ?? $n),
                preg_split('/\s*[\/,;]\s*/u', $linea) ?: [],
            )));

            if ($nombres === []) {
                continue;
            }

            // Primero se comprueba la línea entera; si algo falla, no se crea nada de ella.
            $miembros = [];
            $error = null;
            foreach ($nombres as $nombre) {
                $clave = static::claveNombre($nombre);
                $socio = $socios[$clave] ?? null;
                if ($socio !== null && isset($ocupados[$socio->id])) {
                    $error = "{$nombre} ya está en otro equipo de {$seccion->nombre}";
                    break;
                }
                if (in_array($clave, array_column($miembros, 'clave'), true)) {
                    $error = "{$nombre} aparece dos veces en el mismo equipo";
                    break;
                }
                $miembros[] = ['clave' => $clave, 'nombre' => $nombre, 'socio' => $socio];
            }

            if ($error !== null) {
                $errores[] = $error;

                continue;
            }

            $equipo = $seccion->equipos()->create(['temporada_id' => $temporada->id, 'nombre' => $nombreEquipo]);
            foreach ($miembros as $m) {
                $socio = $m['socio'];
                if ($socio === null) {
                    $socio = $this->socios()->create(['nombre' => $m['nombre']]);
                    $socios[$m['clave']] = $socio;
                    $sociosNuevos[] = $m['nombre'];
                }
                $socio->seccions()->syncWithoutDetaching([$seccion->id]);
                $equipo->socios()->attach($socio->id);
                $ocupados[$socio->id] = true;
            }
            $creados[] = $equipo->load('socios')->etiqueta();
        }

        return compact('creados', 'errores', 'sociosNuevos');
    }

    /** «José  Pérez» y «jose perez» son el mismo socio. */
    private static function claveNombre(string $nombre): string
    {
        return Str::lower(Str::ascii(preg_replace('/\s+/u', ' ', trim($nombre)) ?? $nombre));
    }
}
