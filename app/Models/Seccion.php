<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    /** Qué decide un empate. Si sigue igual, se comparte el puesto (1º, 1º, 3º). */
    public const DESEMPATE_PIEZAS = 'piezas';

    public const DESEMPATE_PESO = 'peso';

    public const DESEMPATE_PIEZA_MAYOR = 'pieza_mayor';

    protected $fillable = [
        'club_id', 'nombre', 'slug', 'criterio',
        'sistema_puntuacion', 'puntos_participacion', 'descartes', 'desempate',
    ];

    /** Desempates que tienen sentido para un criterio: nunca por lo mismo en lo que se empata. */
    public static function desempatesPara(string $criterio): array
    {
        return $criterio === self::CRITERIO_PIEZAS
            ? [self::DESEMPATE_PIEZA_MAYOR => 'La pieza mayor', self::DESEMPATE_PESO => 'Quien más peso sume']
            : [self::DESEMPATE_PIEZA_MAYOR => 'La pieza mayor', self::DESEMPATE_PIEZAS => 'Quien más piezas saque'];
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

            if (! array_key_exists($seccion->desempate ?? '', static::desempatesPara($criterio))) {
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
            'puntos_participacion' => 'integer',
            'descartes' => 'integer',
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
        );
    }

    /** Reglas en lenguaje de club a partir de la configuración (sirve también antes de guardar). */
    public static function resumenReglasDe(
        string $criterio,
        int $puntosParticipacion = 0,
        int $descartes = 0,
        string $sistema = self::SISTEMA_ACUMULADO,
        ?string $desempate = null,
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

        if ($descartes > 0) {
            $frases[] = $descartes === 1
                ? 'No cuenta la peor manga de cada socio.'
                : "No cuentan las {$descartes} peores mangas de cada socio.";
        }

        if ($puntosParticipacion > 0 && $sistema !== self::SISTEMA_PUESTOS) {
            $frases[] = "Cada manga pescada suma además {$puntosParticipacion} puntos.";
        }

        $frases[] = 'Si empatan, gana '.match ($desempate) {
            self::DESEMPATE_PIEZA_MAYOR => 'la pieza mayor',
            self::DESEMPATE_PESO => 'quien más peso sume',
            default => 'quien más piezas saque',
        }.'; si siguen igual, comparten puesto.';

        return implode(' ', $frases);
    }
}
