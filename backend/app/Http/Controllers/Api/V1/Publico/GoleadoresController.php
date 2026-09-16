<?php

namespace App\Http\Controllers\Api\V1\Publico;

use App\Http\Controllers\Controller;
use App\Models\Torneo;
use App\Servicios\Torneos\TablaDeGoleadores;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoleadoresController extends Controller
{
    public function __invoke(Request $peticion, Torneo $torneo, TablaDeGoleadores $goleadores): JsonResponse
    {
        $this->authorize('ver', $torneo);

        // El límite se toma de una lista cerrada para que nadie pueda pedir
        // «los primeros un millón» y tumbar la consulta.
        $limite = (int) $peticion->integer('limite', 25);
        $limite = in_array($limite, [10, 25, 50, 100], true) ? $limite : 25;

        return response()->json([
            'data' => $goleadores->paraTorneo($torneo, $limite),
        ]);
    }
}
