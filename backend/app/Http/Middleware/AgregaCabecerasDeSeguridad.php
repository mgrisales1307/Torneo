<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras que el navegador necesita para no ayudar a un atacante.
 *
 * - nosniff: si un archivo subido pasara por imagen, el navegador no intentará
 *   interpretarlo como HTML o JavaScript.
 * - DENY: nadie puede meter la aplicación en un iframe para superponerle
 *   botones invisibles (clickjacking).
 * - Referrer-Policy: las URL internas no viajan a sitios de terceros.
 */
class AgregaCabecerasDeSeguridad
{
    public function handle(Request $peticion, Closure $siguiente): Response
    {
        $respuesta = $siguiente($peticion);

        $respuesta->headers->set('X-Content-Type-Options', 'nosniff');
        $respuesta->headers->set('X-Frame-Options', 'DENY');
        $respuesta->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $respuesta->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        return $respuesta;
    }
}
