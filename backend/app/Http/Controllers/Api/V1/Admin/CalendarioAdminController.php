<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GenerarCalendarioRequest;
use App\Http\Resources\PartidoResource;
use App\Models\Torneo;
use App\Servicios\Torneos\GeneradorDeCalendario;
use Illuminate\Http\JsonResponse;

/**
 * Generación del calendario de un torneo.
 */
class CalendarioAdminController extends Controller
{
    public function store(
        GenerarCalendarioRequest $peticion,
        Torneo $torneo,
        GeneradorDeCalendario $generador,
    ): JsonResponse {
        $this->authorize('gestionarCalendario', $torneo);

        $creados = $generador->generar($torneo, $peticion->boolean('reiniciar'));

        $partidos = $torneo->partidos()
            ->with(['equipoLocal', 'equipoVisitante'])
            ->orderBy('jornada')
            ->orderBy('id')
            ->get();

        return response()->json([
            'message' => "Calendario generado: $creados partidos.",
            'data' => PartidoResource::collection($partidos),
        ], 201);
    }
}
