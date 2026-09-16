<?php

namespace App\Http\Controllers\Api\V1\Publico;

use App\Http\Controllers\Controller;
use App\Http\Resources\EquipoResource;
use App\Http\Resources\PartidoResource;
use App\Models\Equipo;
use App\Models\Partido;
use Illuminate\Http\JsonResponse;

/**
 * Ficha pública de un equipo: plantilla y partidos.
 */
class EquipoPublicoController extends Controller
{
    public function show(Equipo $equipo): JsonResponse
    {
        $equipo->load([
            'jugadores' => fn ($consulta) => $consulta
                ->orderBy('numero_camiseta')
                ->orderBy('apellidos'),
            'inscripciones' => fn ($consulta) => $consulta->aprobadas()->with('torneo'),
        ]);

        // Los partidos del equipo, juegue de local o de visitante.
        $partidos = Partido::query()
            ->delEquipo($equipo->getKey())
            ->with(['equipoLocal', 'equipoVisitante', 'torneo'])
            ->orderBy('programado_para')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => new EquipoResource($equipo),
            'partidos' => PartidoResource::collection($partidos),
        ]);
    }
}
