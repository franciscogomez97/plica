<?php

namespace App\Support;

use App\Models\Equipo;
use App\Models\Participacion;
use App\Models\Socio;
use Illuminate\Support\Collection;

/**
 * Quien participa en una manga y sale en una clasificación: un socio o, en las
 * secciones por equipos, un equipo. El motor y las vistas hablan con esto y no
 * tienen que saber cuál de los dos es. Dentro de una sección nunca se mezclan,
 * así que `id` identifica al participante en ella.
 */
final class Participante
{
    public readonly int $id;

    /** Cómo se presenta: el nombre del socio, o el del equipo (sin nombre, los de sus socios). */
    public readonly string $nombre;

    public readonly bool $esEquipo;

    public readonly bool $activo;

    /** Ruta de la foto (disco «public»); un equipo no tiene una, tiene las de sus socios. */
    public readonly ?string $foto;

    /** @var Collection<int, Socio> los socios: uno, o los del equipo */
    public readonly Collection $socios;

    public function __construct(public readonly Socio|Equipo $modelo)
    {
        $this->id = (int) $modelo->id;
        $this->esEquipo = $modelo instanceof Equipo;
        $this->nombre = $modelo instanceof Equipo ? $modelo->etiqueta() : $modelo->nombre;
        $this->activo = $modelo instanceof Equipo ? true : (bool) $modelo->activo;
        $this->foto = $modelo instanceof Equipo ? null : $modelo->foto;
        $this->socios = $modelo instanceof Equipo ? $modelo->socios : collect([$modelo]);
    }

    public static function de(Participacion $participacion): self
    {
        return new self($participacion->equipo ?? $participacion->socio);
    }

    /** ¿Es este socio, o está en este equipo? Para el «· tú» de las clasificaciones. */
    public function incluye(?int $socioId): bool
    {
        return $socioId !== null && $this->socios->contains('id', $socioId);
    }

    /** Las fotos de sus socios (rutas), para pintar avatares. */
    public function fotos(): array
    {
        return $this->socios->pluck('foto')->all();
    }

    /**
     * Lo que se pinta en una fila, línea a línea: el nombre del socio; el del
     * equipo si lo tiene; y si no lo tiene, el nombre de cada socio en su línea
     * (aunque la fila salga más alta: que se lea quiénes son).
     *
     * @return string[]
     */
    public function lineas(): array
    {
        if (! $this->esEquipo) {
            return [$this->nombre];
        }

        return filled($this->modelo->nombre) ? [$this->modelo->nombre] : $this->socios->pluck('nombre')->all();
    }

    /** Los socios de un equipo con nombre propio, para ponerlos en pequeño debajo; null si no procede. */
    public function detalleEquipo(): ?string
    {
        return $this->esEquipo && filled($this->modelo->nombre) ? $this->modelo->miembrosTexto() : null;
    }

    /**
     * Versión corta para columnas estrechas (la fija del cuadro en el móvil, las
     * filas de la tarjeta): «Mario L.», el nombre del equipo, o «Mario L. / Sergio R.».
     */
    public function nombreCorto(): string
    {
        if ($this->esEquipo && filled($this->modelo->nombre)) {
            return $this->modelo->nombre;
        }

        return $this->socios->map(fn (Socio $s) => static::abreviar($s->nombre))->implode(' / ');
    }

    /** Segundos nombres habituales: «Miguel Ángel», «José Luis», «María José», «Juan Carlos»… */
    private const SEGUNDOS_NOMBRES = ['angel', 'angela', 'antonio', 'carlos', 'carmen', 'cristina', 'david', 'elena', 'enrique', 'eva', 'fernando',
        'francisco', 'ignacio', 'isabel', 'javier', 'jesus', 'jose', 'josep', 'juan', 'luis', 'luisa', 'manuel', 'mar', 'maria', 'mari', 'mario',
        'miguel', 'pablo', 'pedro', 'pilar', 'rafael', 'ramon', 'rosa', 'teresa', 'vicente', 'alberto', 'andres', 'daniel', 'jorge', 'alejandro',
        'ana', 'belen', 'jaime', 'felipe', 'victor', 'ruben', 'sergio'];

    /** Partículas de apellido: «del Río», «de la Torre», «San Martín»… */
    private const PARTICULAS = ['de', 'del', 'la', 'las', 'los', 'y', 'san', 'santa', 'da', 'do', 'dos', 'das', 'van', 'von', 'di', 'le'];

    /**
     * «Mario López García» → «Mario L.», «Miguel Ángel Toro» → «Miguel Ángel T.»,
     * «Sergio del Río» → «Sergio del R.», «Paco de la Torre» → «Paco de la T.».
     */
    public static function abreviar(string $nombre): string
    {
        $partes = array_values(array_filter(preg_split('/\s+/u', trim($nombre)) ?: []));
        if (count($partes) < 2) {
            return $nombre;
        }

        $llano = fn (string $p): string => mb_strtolower(\Illuminate\Support\Str::ascii($p));
        $i = 1;
        // Nombre compuesto: el segundo es un nombre de pila y aún queda apellido detrás.
        if (count($partes) >= 3 && in_array($llano($partes[1]), self::SEGUNDOS_NOMBRES, true)) {
            $i = 2;
        }
        $nombrePila = implode(' ', array_slice($partes, 0, $i));
        // Partículas del apellido: van enteras, y la inicial es la de la palabra que sigue.
        $particulas = [];
        while (isset($partes[$i + 1]) && in_array($llano($partes[$i]), self::PARTICULAS, true)) {
            $particulas[] = $partes[$i];
            $i++;
        }

        return implode(' ', array_filter([$nombrePila, ...$particulas, mb_substr($partes[$i], 0, 1).'.'], fn ($x) => $x !== ''));
    }
}
