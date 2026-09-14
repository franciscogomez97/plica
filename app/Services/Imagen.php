<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Convierte una imagen subida (PNG, JPG, WebP, GIF) a WebP reducida, con GD
 * y sin dependencias. La usan el logo del club y la foto del socio.
 */
class Imagen
{
    /**
     * Devuelve los bytes WebP: reducida a `$ladoMaximo` píxeles de lado (nunca
     * ampliada) conservando la transparencia; con `$cuadrada`, recortada al
     * centro para que salga cuadrada (para fotos de perfil).
     */
    public static function webp(\SplFileInfo $archivo, int $ladoMaximo, bool $cuadrada = false): string
    {
        $contenido = (string) file_get_contents($archivo->getRealPath());
        $origen = @imagecreatefromstring($contenido);

        if ($origen === false) {
            throw new InvalidArgumentException('El archivo no es una imagen que se pueda leer.');
        }

        $ancho = imagesx($origen);
        $alto = imagesy($origen);

        // Trozo del original que se usa: todo, o el cuadrado central.
        $recorteAncho = $cuadrada ? min($ancho, $alto) : $ancho;
        $recorteAlto = $cuadrada ? min($ancho, $alto) : $alto;
        $recorteX = intdiv($ancho - $recorteAncho, 2);
        $recorteY = intdiv($alto - $recorteAlto, 2);

        $escala = min(1, $ladoMaximo / max($recorteAncho, $recorteAlto));
        $nuevoAncho = max(1, (int) round($recorteAncho * $escala));
        $nuevoAlto = max(1, (int) round($recorteAlto * $escala));

        $destino = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
        imagealphablending($destino, false);
        imagesavealpha($destino, true);
        imagefill($destino, 0, 0, imagecolorallocatealpha($destino, 0, 0, 0, 127));
        imagecopyresampled($destino, $origen, 0, 0, $recorteX, $recorteY, $nuevoAncho, $nuevoAlto, $recorteAncho, $recorteAlto);

        ob_start();
        imagewebp($destino, null, 85);
        $webp = (string) ob_get_clean();

        imagedestroy($origen);
        imagedestroy($destino);

        return $webp;
    }
}
