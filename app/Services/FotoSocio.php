<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * La foto del socio: lo que suba el admin (en la ficha) o el socio (en su
 * perfil) se recorta al centro, se reduce a un cuadrado pequeño y se guarda
 * en WebP en el disco «public». Al cambiarla o al borrar al socio, el archivo
 * viejo se borra (ver Socio::booted).
 */
class FotoSocio
{
    public const LADO = 400;

    public const DISCO = 'public';

    /** Convierte y guarda; devuelve la ruta dentro del disco («socios/3-abc.webp»). */
    public static function guardar(\SplFileInfo $archivo, int $clubId): string
    {
        $ruta = 'socios/'.$clubId.'-'.Str::lower(Str::random(12)).'.webp';
        Storage::disk(self::DISCO)->put($ruta, Imagen::webp($archivo, self::LADO, cuadrada: true));

        return $ruta;
    }

    public static function borrar(?string $ruta): void
    {
        if (filled($ruta)) {
            Storage::disk(self::DISCO)->delete($ruta);
        }
    }
}
