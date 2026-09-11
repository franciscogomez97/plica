<?php

namespace App\Services;

use App\Models\Club;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * El logotipo del club: lo que suba el admin (PNG, JPG, WebP, GIF) se
 * convierte a WebP, se reduce a un tamaño razonable conservando la
 * transparencia y se guarda en el disco «public». Sin dependencias: GD.
 */
class LogoClub
{
    public const LADO_MAXIMO = 512;

    public const DISCO = 'public';

    /** Convierte y guarda; devuelve la ruta dentro del disco («logos/3-abc.webp»). */
    public static function guardar(\SplFileInfo $archivo, Club $club): string
    {
        $contenido = (string) file_get_contents($archivo->getRealPath());
        $origen = @imagecreatefromstring($contenido);

        if ($origen === false) {
            throw new InvalidArgumentException('El archivo no es una imagen que se pueda leer.');
        }

        $ancho = imagesx($origen);
        $alto = imagesy($origen);
        $escala = min(1, self::LADO_MAXIMO / max($ancho, $alto));
        $nuevoAncho = max(1, (int) round($ancho * $escala));
        $nuevoAlto = max(1, (int) round($alto * $escala));

        $destino = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
        imagealphablending($destino, false);
        imagesavealpha($destino, true);
        imagefill($destino, 0, 0, imagecolorallocatealpha($destino, 0, 0, 0, 127));
        imagecopyresampled($destino, $origen, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);

        ob_start();
        imagewebp($destino, null, 85);
        $webp = (string) ob_get_clean();

        imagedestroy($origen);
        imagedestroy($destino);

        $ruta = 'logos/'.$club->id.'-'.Str::lower(Str::random(10)).'.webp';
        Storage::disk(self::DISCO)->put($ruta, $webp);

        return $ruta;
    }

    public static function borrar(?string $ruta): void
    {
        if (filled($ruta)) {
            Storage::disk(self::DISCO)->delete($ruta);
        }
    }
}
