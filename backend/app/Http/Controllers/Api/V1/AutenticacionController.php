<?php

namespace App\Http\Controllers\Api\V1;

use App\Enumeraciones\RolUsuario;
use App\Http\Controllers\Controller;
use App\Http\Requests\Autenticacion\IngresoRequest;
use App\Http\Requests\Autenticacion\RegistroRequest;
use App\Http\Resources\UsuarioResource;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Entrada y salida de la plataforma.
 *
 * La sesión va por cookie firmada y con protección CSRF (Sanctum en modo SPA),
 * no por token en el navegador: un token guardado en localStorage se lo lleva
 * cualquier XSS, una cookie HttpOnly no.
 */
class AutenticacionController extends Controller
{
    /**
     * Quien se registra lo hace para inscribir a su equipo, así que la cuenta
     * nace con el rol de dueño. Los roles de la organización los asigna la
     * administración, nunca un formulario público.
     */
    public function registro(RegistroRequest $peticion): JsonResponse
    {
        $usuario = Usuario::create([
            ...$peticion->safe()->only(['nombre', 'correo', 'telefono']),
            'clave' => $peticion->validated('clave'),
            'rol' => RolUsuario::Dueno,
        ]);

        Auth::login($usuario);
        $peticion->session()->regenerate();

        return response()->json([
            'message' => 'Tu cuenta quedó creada. ¡Bienvenido a Juegos Zone!',
            'usuario' => new UsuarioResource($usuario),
        ], 201);
    }

    public function ingreso(IngresoRequest $peticion): JsonResponse
    {
        $credenciales = [
            'correo' => $peticion->validated('correo'),
            // La clave viaja bajo la llave «password» porque es la que Laravel
            // reconoce como secreta y no convierte en condición de búsqueda.
            // La columna real, `clave`, la declara el modelo Usuario.
            'password' => $peticion->validated('clave'),
        ];

        if (! Auth::attempt($credenciales, $peticion->boolean('recordarme'))) {
            // El mismo mensaje para correo inexistente y clave equivocada: si
            // fueran distintos, se podría averiguar qué correos tienen cuenta.
            throw ValidationException::withMessages([
                'correo' => ['Las credenciales son incorrectas.'],
            ]);
        }

        // Cambiar el identificador de sesión al entrar cierra la puerta a la
        // fijación de sesión.
        $peticion->session()->regenerate();

        return response()->json([
            'message' => 'Sesión iniciada correctamente.',
            'usuario' => new UsuarioResource($peticion->user()),
        ]);
    }

    public function salida(Request $peticion): JsonResponse
    {
        Auth::guard('web')->logout();

        $peticion->session()->invalidate();
        $peticion->session()->regenerateToken();

        return response()->json(['message' => 'Sesión cerrada correctamente.']);
    }

    public function perfil(Request $peticion): JsonResponse
    {
        return response()->json([
            'usuario' => new UsuarioResource($peticion->user()->loadCount('equipos')),
        ]);
    }
}
