<?php

namespace App\Http\Controllers\Api\V1\Publico;

use App\Enumeraciones\EstadoPartido;
use App\Enumeraciones\EstadoTorneo;
use App\Enumeraciones\Juego;
use App\Http\Controllers\Controller;
use App\Http\Resources\PartidoResource;
use App\Http\Resources\TorneoResource;
use App\Models\Partido;
use App\Models\Torneo;
use Illuminate\Http\JsonResponse;

/**
 * Todo lo que necesita la portada en una sola petición: los cinco juegos con
 * sus torneos activos, los torneos destacados y los últimos resultados.
 */
class InicioController extends Controller
{
    private const ESTADOS_ACTIVOS = [
        EstadoTorneo::EnCurso,
        EstadoTorneo::InscripcionesAbiertas,
        EstadoTorneo::InscripcionesCerradas,
    ];

    public function __invoke(): JsonResponse
    {
        $activos = array_map(fn (EstadoTorneo $estado): string => $estado->value, self::ESTADOS_ACTIVOS);

        $porJuego = Torneo::query()
            ->whereIn('estado', $activos)
            ->selectRaw('juego, count(*) as total')
            ->groupBy('juego')
            ->pluck('total', 'juego');

        $torneos = Torneo::query()
            ->whereIn('estado', $activos)
            ->with('campeon')
            ->withCount(['inscripcionesQueOcupanCupo', 'partidos'])
            ->orderByRaw("CASE estado WHEN 'en_curso' THEN 0 WHEN 'inscripciones_abiertas' THEN 1 ELSE 2 END")
            ->orderBy('inicia_en')
            ->limit(8)
            ->get();

        $resultados = Partido::query()
            ->whereIn('torneo_id', Torneo::query()->publicados()->select('id'))
            ->finalizados()
            ->duelos()
            ->with(['equipoA', 'equipoB', 'torneo'])
            ->orderByDesc('finalizado_en')
            ->orderByDesc('id')
            ->limit(6)
            ->get();

        return response()->json([
            'juegos' => array_map(fn (Juego $juego): array => [
                ...$juego->comoArreglo(),
                'torneos_activos' => (int) ($porJuego[$juego->value] ?? 0),
            ], Juego::cases()),
            'torneos' => TorneoResource::collection($torneos),
            'ultimos_resultados' => PartidoResource::collection($resultados),
            'en_juego' => Partido::query()
                ->whereIn('torneo_id', Torneo::query()->publicados()->select('id'))
                ->whereIn('estado', [EstadoPartido::Reportado->value, EstadoPartido::EnDisputa->value, EstadoPartido::PorJugar->value])
                ->duelos()
                ->count(),
        ]);
    }
}
