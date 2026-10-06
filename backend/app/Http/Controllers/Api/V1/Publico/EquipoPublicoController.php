<?php

namespace App\Http\Controllers\Api\V1\Publico;

use App\Http\Controllers\Controller;
use App\Http\Resources\EquipoResource;
use App\Http\Resources\PartidoResource;
use App\Http\Resources\TorneoResource;
use App\Models\Equipo;
use App\Models\Partido;
use App\Models\Torneo;
use Illuminate\Http\JsonResponse;

/**
 * Ficha pública del equipo: plantilla, torneos y últimos partidos.
 */
class EquipoPublicoController extends Controller
{
    public function show(Equipo $equipo): JsonResponse
    {
        $equipo->load(['miembros.cuentasDeJuego'])->loadCount('miembros');

        $torneos = Torneo::query()
            ->publicados()
            ->whereHas('inscripciones', fn ($consulta) => $consulta->aprobadas()->where('equipo_id', $equipo->getKey()))
            ->with('campeon')
            ->withCount(['inscripcionesQueOcupanCupo', 'partidos'])
            ->latest('inicia_en')
            ->limit(12)
            ->get();

        $partidos = Partido::query()
            ->delEquipo($equipo->getKey())
            ->where('es_descanso', false)
            ->with(['equipoA', 'equipoB', 'torneo'])
            ->orderByRaw("CASE estado WHEN 'finalizado' THEN 1 ELSE 0 END")
            ->orderByDesc('finalizado_en')
            ->orderBy('programado_para')
            ->limit(12)
            ->get();

        return response()->json([
            'data' => new EquipoResource($equipo),
            'torneos' => TorneoResource::collection($torneos),
            'partidos' => PartidoResource::collection($partidos),
            'titulos' => Torneo::query()->where('campeon_id', $equipo->getKey())->count(),
        ]);
    }
}
