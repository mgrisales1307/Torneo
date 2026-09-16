<?php

namespace App\Http\Controllers\Api\V1\Publico;

use App\Enumeraciones\EstadoPartido;
use App\Http\Controllers\Controller;
use App\Http\Resources\PartidoResource;
use App\Models\Partido;
use App\Models\Torneo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Calendario y resultados de un torneo.
 */
class CalendarioController extends Controller
{
    public function index(Request $peticion, Torneo $torneo): AnonymousResourceCollection
    {
        $this->authorize('ver', $torneo);

        $partidos = $torneo->partidos()
            ->with(['equipoLocal', 'equipoVisitante'])
            ->when($peticion->filled('jornada'), fn ($consulta) => $consulta->where('jornada', $peticion->integer('jornada')))
            ->when($peticion->filled('equipo'), fn ($consulta) => $consulta->delEquipo($peticion->integer('equipo')))
            ->when(
                $peticion->filled('estado') && EstadoPartido::tryFrom((string) $peticion->string('estado')) !== null,
                fn ($consulta) => $consulta->where('estado', $peticion->string('estado')),
            )
            ->orderBy('jornada')
            ->orderBy('programado_para')
            ->orderBy('id')
            ->get();

        return PartidoResource::collection($partidos)->additional([
            'meta' => [
                'jornadas' => $torneo->partidos()->max('jornada') ?? 0,
            ],
        ]);
    }

    public function show(Partido $partido): JsonResponse
    {
        $this->authorize('ver', $partido->torneo);

        $partido->load([
            'torneo',
            'equipoLocal',
            'equipoVisitante',
            'eventos' => fn ($consulta) => $consulta->orderBy('minuto')->orderBy('id'),
            'eventos.jugador',
        ]);

        return response()->json([
            'data' => new PartidoResource($partido),
        ]);
    }
}
