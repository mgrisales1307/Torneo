<?php

namespace App\Http\Controllers\Api\V1\Publico;

use App\Enumeraciones\EstadoPartido;
use App\Enumeraciones\EstadoTorneo;
use App\Http\Controllers\Controller;
use App\Http\Resources\PartidoResource;
use App\Http\Resources\TorneoResource;
use App\Models\Equipo;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\Torneo;
use Illuminate\Http\JsonResponse;

/**
 * Todo lo que necesita la portada en una sola petición: los torneos vivos, los
 * próximos partidos, los últimos resultados y cuatro cifras de contexto.
 *
 * Es una llamada en vez de cinco porque la portada es lo primero que carga un
 * visitante y conviene que llegue de golpe.
 */
class InicioController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $torneos = Torneo::query()
            ->publicados()
            ->withCount(['inscripcionesQueOcupanCupo', 'partidos'])
            ->whereIn('estado', [
                EstadoTorneo::EnCurso->value,
                EstadoTorneo::InscripcionesAbiertas->value,
                EstadoTorneo::InscripcionesCerradas->value,
            ])
            ->orderByDesc('inicia_en')
            ->limit(6)
            ->get();

        $proximos = Partido::query()
            ->whereIn('torneo_id', Torneo::query()->publicados()->select('id'))
            ->where('estado', EstadoPartido::Programado->value)
            ->whereNotNull('programado_para')
            ->where('programado_para', '>=', now()->subHours(3))
            ->with(['equipoLocal', 'equipoVisitante', 'torneo'])
            ->orderBy('programado_para')
            ->limit(6)
            ->get();

        $resultados = Partido::query()
            ->whereIn('torneo_id', Torneo::query()->publicados()->select('id'))
            ->finalizados()
            ->with(['equipoLocal', 'equipoVisitante', 'torneo'])
            ->orderByDesc('finalizado_en')
            ->limit(6)
            ->get();

        return response()->json([
            'torneos' => TorneoResource::collection($torneos),
            'proximos_partidos' => PartidoResource::collection($proximos),
            'ultimos_resultados' => PartidoResource::collection($resultados),
            'cifras' => [
                'torneos' => Torneo::query()->publicados()->count(),
                'equipos' => Equipo::query()->count(),
                'jugadores' => Jugador::query()->count(),
                'partidos_jugados' => Partido::query()->finalizados()->count(),
            ],
        ]);
    }
}
