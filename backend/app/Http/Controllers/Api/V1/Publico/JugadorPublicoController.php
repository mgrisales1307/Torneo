<?php

namespace App\Http\Controllers\Api\V1\Publico;

use App\Http\Controllers\Controller;
use App\Http\Resources\JugadorResource;
use App\Http\Resources\PartidoResource;
use App\Models\Partido;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;

/**
 * Perfil público de un jugador, por su apodo.
 */
class JugadorPublicoController extends Controller
{
    public function show(Usuario $usuario): JsonResponse
    {
        $usuario->load(['cuentasDeJuego', 'equipos']);

        $equipos = $usuario->equipos->modelKeys();

        $partidos = Partido::query()
            ->finalizados()
            ->duelos()
            ->where(fn ($consulta) => $consulta->whereIn('equipo_a_id', $equipos)->orWhereIn('equipo_b_id', $equipos))
            ->with(['equipoA', 'equipoB', 'torneo'])
            ->orderByDesc('finalizado_en')
            ->limit(10)
            ->get();

        $ganados = Partido::query()->finalizados()->duelos()->whereIn('ganador_id', $equipos)->count();
        $jugados = Partido::query()->finalizados()->duelos()
            ->where(fn ($consulta) => $consulta->whereIn('equipo_a_id', $equipos)->orWhereIn('equipo_b_id', $equipos))
            ->count();

        return response()->json([
            'data' => new JugadorResource($usuario),
            'partidos' => PartidoResource::collection($partidos),
            'cifras' => [
                'partidos' => $jugados,
                'victorias' => $ganados,
                'porcentaje_de_victorias' => $jugados > 0 ? (int) round(100 * $ganados / $jugados) : null,
            ],
        ]);
    }
}
