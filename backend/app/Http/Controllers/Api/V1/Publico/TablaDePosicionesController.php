<?php

namespace App\Http\Controllers\Api\V1\Publico;

use App\Http\Controllers\Controller;
use App\Models\Torneo;
use App\Servicios\Torneos\CalculadoraDePosiciones;
use Illuminate\Http\JsonResponse;

class TablaDePosicionesController extends Controller
{
    public function __invoke(Torneo $torneo, CalculadoraDePosiciones $calculadora): JsonResponse
    {
        $this->authorize('ver', $torneo);

        return response()->json([
            'data' => $calculadora->paraTorneo($torneo),
            'meta' => [
                'torneo' => $torneo->nombre,
                'puntos_por_victoria' => $torneo->puntos_por_victoria,
                'puntos_por_empate' => $torneo->puntos_por_empate,
                'actualizado_en' => now()->toIso8601String(),
            ],
        ]);
    }
}
