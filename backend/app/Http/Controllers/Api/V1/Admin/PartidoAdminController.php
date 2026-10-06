<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enumeraciones\EstadoPartido;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GuardarBattleRoyaleRequest;
use App\Http\Requests\Admin\GuardarResultadoRequest;
use App\Http\Requests\Admin\ProgramarPartidoRequest;
use App\Http\Resources\PartidoResource;
use App\Models\Partido;
use App\Models\Torneo;
use App\Servicios\Torneos\RegistroDeResultados;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * La organización programa partidos, resuelve disputas y carga los
 * resultados de battle royale.
 */
class PartidoAdminController extends Controller
{
    public function __construct(private readonly RegistroDeResultados $resultados) {}

    /**
     * Por defecto, lo que espera a la organización: las disputas primero.
     */
    public function index(Request $peticion): AnonymousResourceCollection
    {
        $this->authorize('administrar', Torneo::class);

        $peticion->validate([
            'estado' => ['nullable', Rule::enum(EstadoPartido::class)],
        ]);

        $partidos = Partido::query()
            ->duelos()
            ->when(
                $peticion->filled('estado'),
                fn ($consulta) => $consulta->where('estado', $peticion->string('estado')),
                fn ($consulta) => $consulta->whereIn('estado', [EstadoPartido::EnDisputa->value, EstadoPartido::Reportado->value]),
            )
            ->when($peticion->filled('torneo'), fn ($consulta) => $consulta->where('torneo_id', $peticion->integer('torneo')))
            ->with(['equipoA', 'equipoB', 'torneo', 'reportes.equipo', 'reportes.usuario'])
            ->orderByRaw("CASE estado WHEN 'en_disputa' THEN 0 ELSE 1 END")
            ->orderBy('updated_at')
            ->paginate(25)
            ->withQueryString();

        return PartidoResource::collection($partidos);
    }

    public function update(ProgramarPartidoRequest $peticion, Partido $partido): JsonResponse
    {
        $this->authorize('resolver', $partido);

        $partido->fill($peticion->validated())->save();

        return response()->json([
            'message' => 'Fecha actualizada.',
            'data' => new PartidoResource($partido->load(['equipoA', 'equipoB'])),
        ]);
    }

    public function resultado(GuardarResultadoRequest $peticion, Partido $partido): JsonResponse
    {
        $this->authorize('resolver', $partido);

        $partido = $this->resultados->resolver($partido, $peticion->user(), $peticion->datosDelResultado());

        return response()->json([
            'message' => 'Resultado guardado.',
            'data' => new PartidoResource($partido->load(['equipoA', 'equipoB', 'reportes'])),
        ]);
    }

    public function battleRoyale(GuardarBattleRoyaleRequest $peticion, Partido $partido): JsonResponse
    {
        $this->authorize('resolver', $partido);

        $partido = $this->resultados->registrarBattleRoyale($partido, $peticion->user(), $peticion->resultados());

        return response()->json([
            'message' => 'Partida cargada.',
            'data' => new PartidoResource($partido->load('resultadosBattleRoyale.equipo')),
        ]);
    }
}
