<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deja pasar solo a los roles indicados en la ruta: `->middleware('rol:admin,empleado')`.
 *
 * Es la primera barrera, la gruesa. La fina —«este equipo es tuyo»— la ponen
 * las políticas de cada modelo.
 */
class VerificaRol
{
    public function handle(Request $peticion, Closure $siguiente, string ...$roles): Response
    {
        $usuario = $peticion->user();

        if ($usuario === null) {
            return response()->json([
                'message' => 'Necesitas iniciar sesión para continuar.',
            ], 401);
        }

        if (! in_array($usuario->rol->value, $roles, true)) {
            return response()->json([
                'message' => 'No tienes permiso para realizar esta acción.',
            ], 403);
        }

        return $siguiente($peticion);
    }
}
