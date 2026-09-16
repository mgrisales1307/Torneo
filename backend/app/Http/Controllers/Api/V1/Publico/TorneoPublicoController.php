<?php

namespace App\Http\Controllers\Api\V1\Publico;

use App\Http\Controllers\Controller;
use App\Http\Resources\EquipoResource;
use App\Http\Resources\TorneoResource;
use App\Models\Torneo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Los torneos tal como los ve cualquier visitante, sin cuenta.
 */
class TorneoPublicoController extends Controller
{
    public function index(Request $peticion): AnonymousResourceCollection
    {
        $torneos = Torneo::query()
            ->publicados()
            ->withCount(['inscripcionesQueOcupanCupo', 'partidos'])
            ->when($peticion->filled('estado'), fn ($consulta) => $consulta->where('estado', $peticion->string('estado')))
            ->when($peticion->filled('buscar'), function ($consulta) use ($peticion): void {
                $termino = '%'.$peticion->string('buscar').'%';
                $consulta->where(fn ($filtro) => $filtro
                    ->where('nombre', 'like', $termino)
                    ->orWhere('ciudad', 'like', $termino));
            })
            ->orderByRaw("CASE estado WHEN 'en_curso' THEN 0 WHEN 'inscripciones_abiertas' THEN 1 WHEN 'inscripciones_cerradas' THEN 2 ELSE 3 END")
            ->orderByDesc('inicia_en')
            ->paginate(12)
            ->withQueryString();

        return TorneoResource::collection($torneos);
    }

    public function show(Torneo $torneo): JsonResponse
    {
        $this->authorize('ver', $torneo);

        $torneo->loadCount(['inscripcionesQueOcupanCupo', 'partidos']);

        return response()->json([
            'data' => new TorneoResource($torneo),
        ]);
    }

    /**
     * Equipos que ya están dentro del torneo.
     */
    public function equipos(Torneo $torneo): AnonymousResourceCollection
    {
        $this->authorize('ver', $torneo);

        $equipos = $torneo->inscripciones()
            ->aprobadas()
            ->with(['equipo' => fn ($consulta) => $consulta->withCount('jugadores')])
            ->get()
            ->pluck('equipo')
            ->filter()
            ->sortBy('nombre')
            ->values();

        return EquipoResource::collection($equipos);
    }
}
