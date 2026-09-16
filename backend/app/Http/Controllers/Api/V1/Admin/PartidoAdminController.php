<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GuardarResultadoRequest;
use App\Http\Requests\Admin\ReprogramarPartidoRequest;
use App\Http\Resources\PartidoResource;
use App\Models\Partido;
use App\Servicios\Torneos\RegistroDeResultados;
use Illuminate\Http\JsonResponse;

/**
 * Programación y resultados de los partidos.
 */
class PartidoAdminController extends Controller
{
    public function __construct(private readonly RegistroDeResultados $resultados) {}

    public function update(ReprogramarPartidoRequest $peticion, Partido $partido): JsonResponse
    {
        $this->authorize('gestionarCalendario', $partido->torneo);

        $this->resultados->reprogramar($partido, $peticion->validated());

        return response()->json([
            'message' => 'Partido actualizado.',
            'data' => new PartidoResource($this->cargarRelaciones($partido)),
        ]);
    }

    public function registrarResultado(GuardarResultadoRequest $peticion, Partido $partido): JsonResponse
    {
        $this->authorize('gestionarCalendario', $partido->torneo);

        $this->resultados->registrar($partido, $peticion->user(), $peticion->datosDelResultado());

        return response()->json([
            'message' => 'Resultado registrado. La tabla de posiciones ya está actualizada.',
            'data' => new PartidoResource($this->cargarRelaciones($partido)),
        ]);
    }

    private function cargarRelaciones(Partido $partido): Partido
    {
        return $partido->load([
            'equipoLocal',
            'equipoVisitante',
            'torneo',
            'eventos' => fn ($consulta) => $consulta->orderBy('minuto')->orderBy('id'),
            'eventos.jugador',
        ]);
    }
}
