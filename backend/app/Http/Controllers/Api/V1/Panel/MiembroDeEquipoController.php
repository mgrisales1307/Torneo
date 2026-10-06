<?php

namespace App\Http\Controllers\Api\V1\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\ActualizarMiembroRequest;
use App\Http\Requests\Panel\UnirseAEquipoRequest;
use App\Http\Resources\EquipoResource;
use App\Models\Equipo;
use App\Models\Usuario;
use App\Servicios\Equipos\GestorDeEquipos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Entrar a un equipo con el código, y los cambios de plantilla que hace el
 * capitán.
 */
class MiembroDeEquipoController extends Controller
{
    public function __construct(private readonly GestorDeEquipos $gestor) {}

    public function unirse(UnirseAEquipoRequest $peticion): JsonResponse
    {
        $equipo = $this->gestor->unirse($peticion->user(), (string) $peticion->validated('codigo'));

        return response()->json([
            'message' => "Ya eres parte de {$equipo->nombre}.",
            'data' => new EquipoResource($equipo->load('miembros')->loadCount('miembros')),
        ]);
    }

    public function update(ActualizarMiembroRequest $peticion, Equipo $equipo, Usuario $usuario): JsonResponse
    {
        $this->authorize('gestionarPlantilla', $equipo);

        if ($peticion->boolean('capitan')) {
            $this->gestor->cederCapitania($equipo, $usuario);
            $mensaje = "{$usuario->apodo} es el nuevo capitán.";
        } else {
            $this->gestor->cambiarPuesto($equipo, $usuario, $peticion->boolean('es_suplente'));
            $mensaje = $peticion->boolean('es_suplente')
                ? "{$usuario->apodo} pasó a suplente."
                : "{$usuario->apodo} pasó a titular.";
        }

        return response()->json([
            'message' => $mensaje,
            'data' => new EquipoResource($equipo->refresh()->load('miembros.cuentasDeJuego')->loadCount('miembros')),
        ]);
    }

    /**
     * El capitán saca a alguien, o un miembro se va por su cuenta.
     */
    public function destroy(Request $peticion, Equipo $equipo, Usuario $usuario): JsonResponse
    {
        $yo = $peticion->user();
        $meVoy = $yo->getKey() === $usuario->getKey();

        if (! $meVoy) {
            $this->authorize('gestionarPlantilla', $equipo);
        }

        $this->gestor->retirar($equipo, $usuario);

        return response()->json([
            'message' => $meVoy ? "Saliste de {$equipo->nombre}." : "{$usuario->apodo} salió del equipo.",
        ]);
    }
}
