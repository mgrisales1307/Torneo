<?php

namespace App\Http\Controllers\Api\V1\Dueno;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dueno\SubirFotoDeJugadorRequest;
use App\Http\Resources\JugadorPrivadoResource;
use App\Models\Jugador;
use App\Servicios\Medios\AlmacenDeImagenes;
use Illuminate\Http\JsonResponse;

class FotoDeJugadorController extends Controller
{
    public function __construct(private readonly AlmacenDeImagenes $imagenes) {}

    public function store(SubirFotoDeJugadorRequest $peticion, Jugador $jugador): JsonResponse
    {
        $this->authorize('actualizar', $jugador);

        $jugador->forceFill([
            'foto_ruta' => $this->imagenes->guardar(
                $peticion->file('foto'),
                'foto_jugador',
                $jugador->foto_ruta,
            ),
        ])->save();

        return response()->json([
            'message' => 'Foto actualizada.',
            'data' => new JugadorPrivadoResource($jugador),
        ]);
    }
}
