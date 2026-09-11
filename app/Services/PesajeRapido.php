<?php

namespace App\Services;

use App\Models\Participacion;
use App\Models\Seccion;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Pesaje rápido: una fila por socio, se teclea lo de la plica y listo.
 *
 * Traduce lo que escribe el admin (texto, con las manías de cada uno:
 * «3.450», «3,450», «58,5 62 45») a capturas en gramos y milímetros
 * enteros, y al revés. Sustituye las capturas de la participación por lo
 * tecleado: peso/piezas → una captura con el total; medida → un pez por
 * captura. Para apuntar pez a pez con notas sigue estando el formulario
 * detallado de la manga.
 */
class PesajeRapido
{
    /** «3450» · «3.450» · «3,450» → 3450 · «» → 0 · cualquier otra cosa → null. */
    public static function gramos(string $texto): ?int
    {
        $texto = trim($texto);

        if ($texto === '') {
            return 0;
        }

        if (preg_match('/^\d+$/', $texto)) {
            return (int) $texto;
        }

        // Separador de miles («3.450») o kilos con tres decimales («3,450»): el mismo número.
        if (preg_match('/^(\d{1,3})[.,](\d{3})$/', $texto, $m)) {
            return (int) ($m[1].$m[2]);
        }

        return null;
    }

    /** «3» → 3 · «» → 0 · cualquier otra cosa → null. */
    public static function entero(string $texto): ?int
    {
        $texto = trim($texto);

        if ($texto === '') {
            return 0;
        }

        return preg_match('/^\d+$/', $texto) ? (int) $texto : null;
    }

    /**
     * Medidas en centímetros separadas por espacios, a milímetros.
     * «58,5 62 45» → [585, 620, 450] · «» → [] · algo que no es un número → null.
     *
     * @return array<int, int>|null
     */
    public static function medidasMm(string $texto): ?array
    {
        $tokens = preg_split('/[\s;]+/u', trim($texto), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $mm = [];

        foreach ($tokens as $token) {
            if (! preg_match('/^(\d+)(?:[.,](\d{1,2}))?$/', $token, $m)) {
                return null;
            }

            $mm[] = (int) round(((float) ($m[1].'.'.($m[2] ?? '0'))) * 10);
        }

        return $mm;
    }

    /**
     * Milímetros a texto de centímetros para la casilla: [585, 620] → «58,5 62».
     *
     * @param  iterable<int, int>  $mm
     */
    public static function medidasTexto(iterable $mm): string
    {
        $partes = [];

        foreach ($mm as $valor) {
            $cm = number_format($valor / 10, 1, ',', '');
            $partes[] = rtrim(rtrim($cm, '0'), ',');
        }

        return implode(' ', $partes);
    }

    /**
     * Lo que va en la fila rápida de una participación, a partir de sus capturas.
     *
     * @return array{piezas: string, peso: string, medidas: string}
     */
    public static function fila(Participacion $participacion): array
    {
        $piezas = $participacion->piezasTotal();
        $peso = $participacion->pesoTotal();
        $medidas = $participacion->capturas
            ->pluck('medida_mm')
            ->filter(fn ($mm) => $mm !== null && $mm > 0);

        return [
            'piezas' => $piezas > 0 ? (string) $piezas : '',
            'peso' => $peso > 0 ? (string) $peso : '',
            'medidas' => static::medidasTexto($medidas),
            'mayor' => $participacion->piezaMayorGramos() > 0 ? (string) $participacion->piezaMayorGramos() : '',
        ];
    }

    /** Total de una fila: «3 piezas · 4,350 kg», «2 peces · 120,5 cm» o «Sin capturas». */
    public static function total(Participacion $participacion, string $criterio): string
    {
        if ($participacion->capturas->isEmpty()) {
            return 'Sin capturas';
        }

        if ($criterio === Seccion::CRITERIO_MEDIDA) {
            $peces = $participacion->capturas->count();
            $mayor = $peces > 1 && $participacion->piezaMayorMm() > 0 ? ' · mayor '.Scoring::formatMedida($participacion->piezaMayorMm()) : '';

            return ($peces === 1 ? '1 pez' : "{$peces} peces").' · '.Scoring::formatMedida($participacion->medidaTotal()).$mayor;
        }

        $piezas = $participacion->piezasTotal();
        $mayor = $piezas !== 1 && $participacion->piezaMayorGramos() > 0 ? ' · mayor '.Scoring::formatPeso($participacion->piezaMayorGramos()) : '';

        return ($piezas === 1 ? '1 pieza' : "{$piezas} piezas").' · '.Scoring::formatPeso($participacion->pesoTotal()).$mayor;
    }

    /**
     * Sustituye las capturas de la participación por lo tecleado en la fila.
     *
     * @param  array{piezas?: string, peso?: string, medidas?: string}  $fila
     *
     * @throws InvalidArgumentException con el mensaje que debe ver el admin
     */
    public static function aplicar(Participacion $participacion, string $criterio, array $fila): void
    {
        if ($criterio === Seccion::CRITERIO_MEDIDA) {
            $mm = static::medidasMm((string) ($fila['medidas'] ?? ''));

            if ($mm === null) {
                throw new InvalidArgumentException('Medidas en cm separadas por espacios, p. ej. «58,5 62 45».');
            }

            DB::transaction(function () use ($participacion, $mm): void {
                $participacion->capturas()->delete();

                foreach ($mm as $medida) {
                    $participacion->capturas()->create(['piezas' => 1, 'peso_gramos' => 0, 'medida_mm' => $medida]);
                }
            });
        } else {
            $piezas = static::entero((string) ($fila['piezas'] ?? ''));
            $gramos = static::gramos((string) ($fila['peso'] ?? ''));

            if ($piezas === null) {
                throw new InvalidArgumentException('Piezas: un número entero, p. ej. «3».');
            }

            if ($gramos === null) {
                throw new InvalidArgumentException('Peso en gramos, p. ej. «3450» (o «3,450»).');
            }

            $mayor = static::gramos((string) ($fila['mayor'] ?? ''));

            if ($mayor === null) {
                throw new InvalidArgumentException('Pieza mayor en gramos, p. ej. «2100».');
            }

            if ($gramos > 0 && $mayor > $gramos) {
                throw new InvalidArgumentException('La pieza mayor no puede pesar más que el total.');
            }

            // Un solo pez: la pieza mayor es ese pez, no hay que teclearla.
            if ($piezas === 1 && $mayor === 0) {
                $mayor = $gramos;
            }

            $hayCaptura = $piezas > 0 || $gramos > 0;

            // Piezas y peso se guardan tal cual: si falta uno, el admin lo ve en
            // el total y lo corrige. Inventar «1 pieza» impediría vaciar la fila.
            DB::transaction(function () use ($participacion, $piezas, $gramos, $mayor, $hayCaptura): void {
                $participacion->capturas()->delete();

                if ($hayCaptura) {
                    $participacion->capturas()->create(['piezas' => $piezas, 'peso_gramos' => $gramos, 'medida_mm' => null]);
                }

                $participacion->update(['pieza_mayor_gramos' => $hayCaptura && $mayor > 0 ? $mayor : null]);
            });
        }

        $participacion->load('capturas');
    }
}
