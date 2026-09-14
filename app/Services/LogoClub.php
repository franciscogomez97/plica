<?php

namespace App\Services;

use App\Models\Club;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * El logotipo del club: lo que suba el admin (PNG, JPG, WebP, GIF) se
 * convierte a WebP (Imagen), se reduce a un tamaño razonable conservando la
 * transparencia y se guarda en el disco «public».
 */
class LogoClub
{
    public const LADO_MAXIMO = 512;

    public const DISCO = 'public';

    /** Convierte y guarda; devuelve la ruta dentro del disco («logos/3-abc.webp»). */
    public static function guardar(\SplFileInfo $archivo, Club $club): string
    {
        $ruta = 'logos/'.$club->id.'-'.Str::lower(Str::random(10)).'.webp';
        Storage::disk(self::DISCO)->put($ruta, Imagen::webp($archivo, self::LADO_MAXIMO));

        return $ruta;
    }

    public static function borrar(?string $ruta): void
    {
        if (filled($ruta)) {
            Storage::disk(self::DISCO)->delete($ruta);
        }
    }
}
