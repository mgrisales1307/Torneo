<?php

namespace App\Http\Controllers\Api\V1\Publico;

use App\Http\Controllers\Controller;
use App\Http\Resources\EquipoResumenResource;
use App\Http\Resources\FaseResource;
use App\Http\Resources\TorneoResource;
use App\Models\Fase;
use App\Models\Inscripcion;
use App\Models\Torneo;
use App\Servicios\Torneos\TablasDeClasificacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Los torneos tal como los ve cualquier visitante, sin cuenta.
 */
class TorneoPublicoController extends Controller
{
    public function __construct(private readonly TablasDeClasificacion $tablas) {}

    public function index(Request $peticion): AnonymousResourceCollection
    {
        $torneos = Torneo::query()
            ->publicados()
            ->with('campeon')
            ->withCount(['inscripcionesQueOcupanCupo', 'partidos'])
            ->when($peticion->filled('juego'), fn ($consulta) => $consulta->where('juego', $peticion->string('juego')))
            ->when($peticion->filled('estado'), fn ($consulta) => $consulta->where('estado', $peticion->string('estado')))
            ->when($peticion->filled('buscar'), fn ($consulta) => $consulta->where('nombre', 'like', '%'.$peticion->string('buscar').'%'))
            ->orderByRaw("CASE estado WHEN 'en_curso' THEN 0 WHEN 'inscripciones_abiertas' THEN 1 WHEN 'inscripciones_cerradas' THEN 2 ELSE 3 END")
            ->orderByDesc('inicia_en')
            ->paginate(18)
            ->withQueryString();

        return TorneoResource::collection($torneos);
    }

    public function show(Torneo $torneo): JsonResponse
    {
        $this->authorize('ver', $torneo);

        return response()->json([
            'data' => new TorneoResource(
                $torneo->load('campeon')->loadCount(['inscripcionesQueOcupanCupo', 'partidos'])
            ),
        ]);
    }

    /**
     * Equipos que ya están dentro del torneo, con su semilla si ya empezó.
     */
    public function equipos(Torneo $torneo): JsonResponse
    {
        $this->authorize('ver', $torneo);

        $inscripciones = $torneo->inscripciones()
            ->aprobadas()
            ->with(['equipo' => fn ($consulta) => $consulta->withCount('miembros')])
            ->orderByRaw('semilla IS NULL, semilla')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $inscripciones->map(fn (Inscripcion $inscripcion): array => [
                ...(new EquipoResumenResource($inscripcion->equipo))->resolve(),
                'miembros_count' => $inscripcion->equipo->miembros_count,
                'semilla' => $inscripcion->semilla,
                'grupo' => $inscripcion->grupo,
                'hizo_check_in' => $inscripcion->hizoCheckIn(),
            ]),
        ]);
    }

    /**
     * Las fases con sus partidos y, en las que se ordenan por puntos, su
     * tabla. Es todo lo que necesita la pantalla del cuadro.
     */
    public function cuadro(Torneo $torneo): AnonymousResourceCollection
    {
        $this->authorize('ver', $torneo);

        $fases = $torneo->fases()
            ->with(['partidos' => fn ($consulta) => $consulta
                ->with(['equipoA', 'equipoB', 'resultadosBattleRoyale.equipo'])
                ->orderBy('llave')
                ->orderBy('grupo')
                ->orderBy('ronda')
                ->orderBy('numero')])
            ->get();

        return FaseResource::collection($fases)->additional([
            'tablas' => $fases->mapWithKeys(fn (Fase $fase): array => [$fase->id => $this->tablas->deFase($fase)]),
        ]);
    }
}
