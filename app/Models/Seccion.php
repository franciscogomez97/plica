<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Seccion extends Model
{
    public const CRITERIO_PESO = 'peso';

    public const CRITERIO_MEDIDA = 'medida';

    public const CRITERIO_PIEZAS = 'piezas';

    public const CRITERIOS = [
        self::CRITERIO_PESO => 'Peso',
        self::CRITERIO_MEDIDA => 'Medida',
        self::CRITERIO_PIEZAS => 'Nº de piezas',
    ];

    public const SISTEMA_ACUMULADO = 'acumulado';

    public const SISTEMA_PUESTOS = 'puestos';

    public const SISTEMAS = [
        self::SISTEMA_ACUMULADO => 'Acumulado bruto (gana quien más suma)',
        self::SISTEMA_PUESTOS => 'Por puestos (gana quien menos suma)',
    ];

    /**
     * Qué pasa con un empate, en las mangas y en el ranking. Una sola regla:
     * o se desempata por algo (y si siguen igual, comparten puesto), o no se
     * desempata: comparten el puesto o, por puestos, se reparten el promedio.
     */
    public const DESEMPATE_PIEZAS = 'piezas';

    public const DESEMPATE_PESO = 'peso';

    public const DESEMPATE_PIEZA_MAYOR = 'pieza_mayor';

    /** El segundo criterio oficial de la federación tras la pieza mayor: quien menos capturas haya necesitado. */
    public const DESEMPATE_MENOS_PIEZAS = 'menos_piezas';

    public const DESEMPATE_COMPARTIDO = 'compartido';

    public const DESEMPATE_PROMEDIO = 'promedio';

    /**
     * Por puestos: qué se lleva quien va y no pesca (el «bolo»). C = los que
     * pescaron en la manga, N = los que fueron.
     */
    public const BOLO_MEDIA = 'media';           // ((C + 1) + N) / 2: la media de los puestos que quedan (federación)

    public const BOLO_PRIMER_LIBRE = 'primer_libre'; // C + 1

    public const BOLO_ULTIMO = 'ultimo';         // N

    public const BOLO_FIJO = 'fijo';             // puntos_bolo

    public const BOLO_AUSENCIA = 'ausencia';     // lo mismo que no ir

    public const BOLOS = [
        self::BOLO_MEDIA => 'La media de los puestos que quedan (federación)',
        self::BOLO_PRIMER_LIBRE => 'El primer puesto libre, todos igual',
        self::BOLO_ULTIMO => 'El último puesto, todos igual',
        self::BOLO_FIJO => 'Un número fijo de puntos',
        self::BOLO_AUSENCIA => 'Lo mismo que una ausencia',
    ];

    protected $fillable = [
        'club_id', 'nombre', 'slug', 'criterio', 'numero_socios',
        'sistema_puntuacion', 'puntos_participacion', 'puntos_no_asistencia', 'bolo', 'puntos_bolo', 'descartes', 'descartes_ausencias', 'desempate',
    ];

    /**
     * Las opciones de empate que tienen sentido: nunca por lo mismo en lo que se
     * empata, y «se reparten el promedio» solo cuando se suman puestos.
     */
    public static function desempatesPara(string $criterio, string $sistema = self::SISTEMA_ACUMULADO): array
    {
        $porAlgo = $criterio === self::CRITERIO_PIEZAS
            ? [self::DESEMPATE_PIEZA_MAYOR => 'La pieza mayor', self::DESEMPATE_PESO => 'Quien más peso sume']
            : [
                self::DESEMPATE_PIEZA_MAYOR => 'La pieza mayor',
                self::DESEMPATE_PIEZAS => 'Quien más piezas saque',
                self::DESEMPATE_MENOS_PIEZAS => 'Quien menos piezas haya sacado',
            ];

        return $sistema === self::SISTEMA_PUESTOS
            ? $porAlgo + [
                self::DESEMPATE_COMPARTIDO => 'Nadie: comparten el mejor puesto (los dos el 18)',
                self::DESEMPATE_PROMEDIO => 'Nadie: se reparten el promedio de sus puestos (18,5 cada uno)',
            ]
            : $porAlgo + [self::DESEMPATE_COMPARTIDO => 'Nadie: comparten el puesto'];
    }

    /** Los empates que no se desempatan por nada. */
    public static function sinDesempate(?string $desempate): bool
    {
        return in_array($desempate, [self::DESEMPATE_COMPARTIDO, self::DESEMPATE_PROMEDIO], true);
    }

    public static function desempatePorDefecto(string $criterio): string
    {
        return $criterio === self::CRITERIO_PIEZAS ? self::DESEMPATE_PESO : self::DESEMPATE_PIEZAS;
    }

    /**
     * El slug (para el enlace público /c/club/orilla) se genera una sola vez
     * y no cambia al renombrar: un enlace compartido no debe morir nunca.
     * El desempate se corrige si no encaja con el criterio.
     */
    protected static function booted(): void
    {
        static::saving(function (Seccion $seccion): void {
            $criterio = $seccion->criterio ?? self::CRITERIO_PESO;
            $sistema = $seccion->sistema_puntuacion ?? self::SISTEMA_ACUMULADO;

            if (! array_key_exists($seccion->desempate ?? '', static::desempatesPara($criterio, $sistema))) {
                $seccion->desempate = static::desempatePorDefecto($criterio);
            }

            if (blank($seccion->slug)) {
                $seccion->slug = static::slugLibre($seccion);
            }
        });
    }

    private static function slugLibre(Seccion $seccion): string
    {
        $base = Str::slug($seccion->nombre) ?: 'seccion';
        $slug = $base;

        for ($i = 2; static::query()
            ->where('club_id', $seccion->club_id)
            ->where('slug', $slug)
            ->when($seccion->exists, fn ($q) => $q->whereKeyNot($seccion->getKey()))
            ->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    /** Enlace público del ranking de esta sección: cualquiera con el enlace lo ve. */
    public function urlPublica(): string
    {
        return route('club.seccion', ['club' => $this->club->slug, 'seccion' => $this->slug]);
    }

    protected function casts(): array
    {
        return [
            'numero_socios' => 'integer',
            'puntos_participacion' => 'integer',
            'puntos_no_asistencia' => 'integer',
            'puntos_bolo' => 'integer',
            'descartes' => 'integer',
            'descartes_ausencias' => 'boolean',
        ];
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function participacions(): HasMany
    {
        return $this->hasMany(Participacion::class);
    }

    /** Los socios de la sección: los que han pescado alguna manga de ella y los que el club marque a mano. */
    public function socios(): BelongsToMany
    {
        return $this->belongsToMany(Socio::class, 'seccion_socio')->withTimestamps();
    }

    public function mangas(): HasMany
    {
        return $this->hasMany(Manga::class);
    }

    /** Las reglas de esta sección en una frase: formulario, listado y rankings enseñan lo mismo. */
    public function resumenReglas(): string
    {
        return static::resumenReglasDe(
            $this->criterio ?? self::CRITERIO_PESO,
            (int) $this->puntos_participacion,
            (int) $this->descartes,
            $this->sistema_puntuacion ?? self::SISTEMA_ACUMULADO,
            $this->desempate ?? static::desempatePorDefecto($this->criterio ?? self::CRITERIO_PESO),
            (int) $this->puntos_no_asistencia,
            (bool) $this->descartes_ausencias,
            $this->bolo ?? self::BOLO_MEDIA,
            (int) $this->puntos_bolo,
        );
    }

    /** Reglas en lenguaje de club a partir de la configuración (sirve también antes de guardar). */
    public static function resumenReglasDe(
        string $criterio,
        int $puntosParticipacion = 0,
        int $descartes = 0,
        string $sistema = self::SISTEMA_ACUMULADO,
        ?string $desempate = null,
        int $puntosNoAsistencia = 0,
        bool $descartesAusencias = false,
        string $bolo = self::BOLO_MEDIA,
        int $puntosBolo = 0,
    ): string {
        $desempate ??= static::desempatePorDefecto($criterio);
        $frases = [
            match ($criterio) {
                self::CRITERIO_MEDIDA => 'Cada manga la gana quien más centímetros suma (un pez por línea).',
                self::CRITERIO_PIEZAS => 'Cada manga la gana quien más piezas saca.',
                default => 'Cada manga la gana quien más peso saca.',
            },
            $sistema === self::SISTEMA_PUESTOS
                ? 'El ranking suma los puestos de cada manga: gana quien menos suma.'
                : 'El ranking suma '.match ($criterio) {
                    self::CRITERIO_MEDIDA => 'los centímetros',
                    self::CRITERIO_PIEZAS => 'las piezas',
                    default => 'el peso',
                }.' de todas las mangas.',
        ];

        if ($sistema === self::SISTEMA_PUESTOS) {
            $frases[] = 'Ir y no pescar (bolo) '.match ($bolo) {
                self::BOLO_PRIMER_LIBRE => 'vale el primer puesto libre, el mismo para todos los bolos.',
                self::BOLO_ULTIMO => 'vale el último puesto de esa manga, el mismo para todos los bolos.',
                self::BOLO_FIJO => "vale {$puntosBolo} puntos.",
                self::BOLO_AUSENCIA => 'cuesta lo mismo que no ir.',
                default => 'vale la media de los puestos que quedan tras los que pescaron.',
            };
            $frases[] = $puntosNoAsistencia > 0
                ? "No ir a una manga cuesta {$puntosNoAsistencia} puntos."
                : 'No ir a una manga cuesta el último puesto de esa manga más uno.';
        }

        if ($descartes > 0) {
            $frases[] = ($descartes === 1
                ? 'No cuenta la peor manga de cada socio'
                : "No cuentan las {$descartes} peores mangas de cada socio")
                .($descartesAusencias ? ', y no ir a una manga cuenta como la peor.' : '.');
        }

        if ($puntosParticipacion > 0 && $sistema !== self::SISTEMA_PUESTOS) {
            $frases[] = "Cada manga a la que se va suma además {$puntosParticipacion} puntos de asistencia.";
        }

        // Puntos por no ir: solo para quien ya está en el ranking (ha pescado alguna manga).
        if ($puntosNoAsistencia !== 0 && $sistema !== self::SISTEMA_PUESTOS) {
            $frases[] = $puntosNoAsistencia > 0
                ? "Cada ausencia suma {$puntosNoAsistencia} puntos."
                : 'Cada ausencia resta '.abs($puntosNoAsistencia).' puntos.';
        }

        $frases[] = match ($desempate) {
            self::DESEMPATE_PROMEDIO => 'Si empatan en una manga, se reparten el promedio de sus puestos; si empatan en el ranking, comparten puesto.',
            self::DESEMPATE_COMPARTIDO => 'Si empatan, comparten puesto.',
            default => 'Si empatan, gana '.match ($desempate) {
                self::DESEMPATE_PIEZA_MAYOR => 'la pieza mayor',
                self::DESEMPATE_PESO => 'quien más peso sume',
                self::DESEMPATE_MENOS_PIEZAS => 'quien menos piezas haya sacado',
                default => 'quien más piezas saque',
            }.'; si siguen igual, comparten puesto.',
        };

        return implode(' ', $frases);
    }
}
