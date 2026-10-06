<?php

namespace App\Http\Controllers\Api\V1\Panel;

use App\Enumeraciones\EstadoPartido;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\ReportarResultadoRequest;
use App\Http\Resources\PartidoResource;
use App\Models\Equipo;
use App\Models\Partido;
use App\Policies\PartidoPolicy;
use App\Servicios\Torneos\RegistroDeResultados;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Los partidos de mis equipos que esperan algo: jugarse, un reporte o la
 * confirmación del rival.
 */
class PartidoController extends Controller
{
    public function __construct(private readonly RegistroDeResultados $resultados) {}

    public function index(Request $peticion): AnonymousResourceCollection
    {
        $equipos = Equipo::query()->delMiembro($peticion->user())->pluck('id')->all();

        $partidos = Partido::query()
            ->where(fn ($consulta) => $consulta->whereIn('equipo_a_id', $equipos)->orWhereIn('equipo_b_id', $equipos))
            ->whereIn('estado', [
                EstadoPartido::PorJugar->value,
                EstadoPartido::Reportado->value,
                EstadoPartido::EnDisputa->value,
            ])
            ->duelos()
            ->with(['equipoA', 'equipoB', 'torneo', 'reportes'])
            ->orderByRaw('programado_para IS NULL, programado_para')
            ->get();

        return PartidoResource::collection($partidos);
    }

    public function reportar(ReportarResultadoRequest $peticion, Partido $partido): JsonResponse
    {
        $this->authorize('reportar', $partido);

        /** @var Equipo $equipo */
        $equipo = app(PartidoPolicy::class)->equipoQueCapitanea($peticion->user(), $partido);

        $partido = $this->resultados->reportar(
            $partido,
            $equipo,
            $peticion->user(),
            $peticion->datosDelReporte(),
            $peticion->file('captura'),
        );

        return response()->json([
            'message' => match ($partido->estado) {
                EstadoPartido::Finalizado => 'Resultado confirmado por los dos equipos.',
                EstadoPartido::EnDisputa => 'Los reportes no coinciden: la organización revisará las capturas.',
                default => 'Resultado reportado. Falta que el rival lo confirme.',
            },
            'data' => new PartidoResource($partido->load(['equipoA', 'equipoB', 'reportes'])),
        ]);
    }
}
