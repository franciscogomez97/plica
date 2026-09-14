<?php

namespace App\Services;

use App\Models\Club;
use App\Models\Manga;
use App\Models\Seccion;
use App\Models\Temporada;
use Illuminate\Support\Collection;

/**
 * Textos listos para pegar en el grupo de WhatsApp del club. El enlace no va
 * dentro del texto: lo añade el botón, porque la hoja de compartir del móvil
 * ya lo pone ella sola y saldría dos veces.
 */
class Compartir
{
    /** @param  object{nombre: string, criterio: string, sistema: string, puntosParticipacion: int, filas: Collection}  $grupo */
    public static function textoRanking(Club $club, Temporada $temporada, object $grupo): string
    {
        $lineas = ["🏆 Ranking {$grupo->nombre} · {$temporada->nombre}", $club->nombre, ''];

        foreach ($grupo->filas->take(3) as $fila) {
            $lineas[] = "{$fila->puesto}º {$fila->socio->nombre} · ".static::valorRanking($grupo, $fila);
        }

        if ($grupo->filas->count() > 3) {
            $lineas[] = '… y '.($grupo->filas->count() - 3).' más';
        }

        if (($grupo->piezaMayor ?? null) !== null) {
            $lineas[] = "🐟 Pieza mayor de la temporada: {$grupo->piezaMayor->socio->nombre} · {$grupo->piezaMayor->texto} ({$grupo->piezaMayor->manga->nombre})";
        }

        return implode("\n", $lineas);
    }

    /** @param  Collection<int, object{nombre: string, criterio: string, filas: Collection}>  $grupos */
    public static function textoManga(Manga $manga, Collection $grupos): string
    {
        $lineas = ['🎣 '.$manga->nombre.' · '.$manga->fecha->format('d/m/Y').($manga->lugar ? " · {$manga->lugar}" : '')];

        foreach ($grupos as $grupo) {
            $lineas[] = '';
            $lineas[] = "{$grupo->nombre}:";

            foreach ($grupo->filas->take(3) as $fila) {
                $lineas[] = "{$fila->puesto}º {$fila->socio->nombre} · ".Scoring::valorPrincipal($grupo->criterio, $fila);
            }

            if ($grupo->filas->count() > 3) {
                $lineas[] = '… y '.($grupo->filas->count() - 3).' más';
            }

            if (($grupo->piezaMayor ?? null) !== null) {
                $lineas[] = "🐟 Pieza mayor: {$grupo->piezaMayor->socio->nombre} · {$grupo->piezaMayor->texto}";
            }
        }

        return implode("\n", $lineas);
    }

    /**
     * Convocatoria de una manga para el grupo del club. El admin la retoca en
     * un cuadro de texto antes de compartirla, así que aquí sí va el enlace:
     * es donde los socios marcan «asistiré».
     */
    public static function textoConvocatoria(Manga $manga): string
    {
        $fecha = $manga->fecha->locale('es')->isoFormat('dddd D [de] MMMM');

        $lineas = [
            'Buenas a todos 👋',
            "El {$fecha} se celebra la manga «{$manga->nombre}»"
                .($manga->seccion ? " de {$manga->seccion->nombre}" : '')
                .($manga->lugar ? " en {$manga->lugar}" : '')
                .($manga->horario() ? ", {$manga->horario()}" : '')
                .'.',
        ];

        if ($manga->ubicacion_url) {
            $lineas[] = "📍 Cómo llegar: {$manga->ubicacion_url}";
        }

        if ($manga->quedada()) {
            $lineas[] = "🤝 Quedada previa {$manga->quedada()}";

            if ($manga->quedada_url) {
                $lineas[] = "📍 Cómo llegar a la quedada: {$manga->quedada_url}";
            }
        }

        $lineas[] = '';
        $lineas[] = 'Confirmad si venís en este enlace:';
        $lineas[] = $manga->urlPublica();

        return implode("\n", $lineas);
    }

    /** Resumen corto para la vista previa del enlace en WhatsApp (og:description). */
    public static function resumen(object $grupo): string
    {
        return $grupo->filas
            ->take(3)
            ->map(fn (object $fila) => "{$fila->puesto}º {$fila->socio->nombre}")
            ->implode(' · ');
    }

    private static function valorRanking(object $grupo, object $fila): string
    {
        $conPuntos = ($grupo->sistema ?? Seccion::SISTEMA_ACUMULADO) === Seccion::SISTEMA_PUESTOS
            || ($grupo->puntosParticipacion ?? 0) > 0
            || ($grupo->puntosNoAsistencia ?? 0) !== 0;

        return $conPuntos
            ? Scoring::formatPuntos($fila->puntos).' pts'
            : Scoring::valorRanking($grupo->criterio, $fila->puntos);
    }
}
