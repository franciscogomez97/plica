<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras de seguridad básicas en todas las respuestas: nadie mete Plica en
 * un iframe ajeno, el navegador no adivina tipos de contenido y no se filtra
 * la URL completa al salir a otros sitios (los enlaces públicos llevan slugs
 * de club y los de acceso, tokens). HSTS lo pone Nginx, que es quien sabe si
 * hay HTTPS.
 */
class CabecerasSeguridad
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        return $response;
    }
}
