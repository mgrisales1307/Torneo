<?php

namespace App\Http\Controllers\Api\V1\Publico;

use App\Http\Controllers\Controller;
use App\Http\Resources\PartidoResource;
use App\Models\Partido;
use App\Models\Usuario;
use App\Policies\PartidoPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ficha de un partido. Los reportes y sus capturas solo los ven los dos
 * equipos y la organización; el marcador final lo ve todo el mundo.
 */
class PartidoPublicoController extends Controller
{
    public function show(Request $peticion, Partido $partido): JsonResponse
    {
        $this->authorize('ver', $partido->torneo);

        $partido->load(['equipoA', 'equipoB', 'torneo', 'fase', 'resultadosBattleRoyale.equipo', 'resolutor']);

        /** @var Usuario|null $usuario */
        $usuario = $peticion->user('sanctum');
        $politica = app(PartidoPolicy::class);

        if ($usuario !== null && $politica->verReportes($usuario, $partido)) {
            $partido->load(['reportes.equipo', 'reportes.usuario']);
        }

        return response()->json([
            'data' => new PartidoResource($partido),
            'puedo_reportar' => $usuario !== null && $politica->reportar($usuario, $partido),
            'mi_equipo_id' => $usuario !== null ? $politica->equipoQueCapitanea($usuario, $partido)?->getKey() : null,
            'puedo_resolver' => $usuario?->perteneceAlStaff() ?? false,
        ]);
    }
}
