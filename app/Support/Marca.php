<?php

namespace App\Support;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * La marca de Plica: el logotipo (public/brand, generado desde el original
 * «logo plica.png») en todas sus formas. Los clubes ponen su propio logo en
 * sus paneles; esto es lo que se ve donde no hay club (login, landing, web).
 */
class Marca
{
    public const LOGO = '/brand/plica-256.webp';

    public const OG = '/brand/og.png';

    public const FAVICON = '/icons/favicon-32.png';

    /** Logo + nombre para la cabecera de los paneles cuando no hay club en sesión. */
    public static function plica(): Htmlable
    {
        return new HtmlString(
            '<span style="display:inline-flex; align-items:center; gap:.55rem; height:100%">'
            .'<img src="'.e(asset(self::LOGO)).'" alt="Plica" style="height:100%; width:auto; object-fit:contain">'
            .'<span style="font-weight:800; font-size:1.15rem; letter-spacing:-.01em">Plica</span>'
            .'</span>'
        );
    }

    /** Etiquetas de icono para cualquier <head>. */
    public static function iconos(): string
    {
        return '<link rel="icon" type="image/png" sizes="32x32" href="'.e(asset('/icons/favicon-32.png')).'">'
            .'<link rel="icon" type="image/png" sizes="16x16" href="'.e(asset('/icons/favicon-16.png')).'">'
            .'<link rel="apple-touch-icon" sizes="180x180" href="'.e(asset('/icons/apple-touch-icon.png')).'">'
            .'<link rel="manifest" href="'.e(asset('/manifest.webmanifest')).'">'
            .'<meta name="apple-mobile-web-app-capable" content="yes">'
            .'<meta name="apple-mobile-web-app-title" content="Plica">'
            .'<meta name="theme-color" content="#059669">';
    }
}
