<?php

namespace App\Http\Controllers\Api\V1\Publico;

use App\Enumeraciones\Juego;
use App\Http\Controllers\Controller;
use App\Http\Resources\TorneoResource;
use App\Models\Torneo;
use App\Servicios\Torneos\RankingDeEquipos;
use Illuminate\Http\JsonResponse;

/**
 * Los cinco juegos de la plataforma, y la página de cada uno: sus torneos y
 * su ranking.
 */
class JuegoController extends Controller
{
    public function __construct(private readonly RankingDeEquipos $ranking) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => array_map(fn (Juego $juego): array => $juego->comoArreglo(), Juego::cases()),
        ]);
    }

    public function show(Juego $juego): JsonResponse
    {
        $torneos = Torneo::query()
            ->publicados()
            ->where('juego', $juego->value)
            ->with('campeon')
            ->withCount(['inscripcionesQueOcupanCupo', 'partidos'])
            ->orderByRaw("CASE estado WHEN 'en_curso' THEN 0 WHEN 'inscripciones_abiertas' THEN 1 WHEN 'inscripciones_cerradas' THEN 2 ELSE 3 END")
            ->orderByDesc('inicia_en')
            ->limit(30)
            ->get();

        return response()->json([
            'juego' => $juego->comoArreglo(),
            'torneos' => TorneoResource::collection($torneos),
            'ranking' => $this->ranking->paraJuego($juego, 25),
        ]);
    }
}
