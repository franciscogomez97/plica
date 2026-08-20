<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    protected $fillable = [
        'club_id', 'nombre', 'criterio',
        'sistema_puntuacion', 'puntos_participacion', 'descartes',
    ];

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
}
