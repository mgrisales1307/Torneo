<?php

namespace App\Http\Controllers\Api\V1\Dueno;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dueno\SubirImagenDeEquipoRequest;
use App\Http\Resources\EquipoResource;
use App\Models\Equipo;
use App\Servicios\Medios\AlmacenDeImagenes;
use Illuminate\Http\JsonResponse;

/**
 * Escudo y uniformes del equipo.
 */
class ImagenDeEquipoController extends Controller
{
    public function __construct(private readonly AlmacenDeImagenes $imagenes) {}

    public function store(SubirImagenDeEquipoRequest $peticion, Equipo $equipo): JsonResponse
    {
        $this->authorize('actualizar', $equipo);

        $columna = $peticion->columnaDestino();

        $equipo->forceFill([
            $columna => $this->imagenes->guardar(
                $peticion->file('imagen'),
                $peticion->tipoDeLimites(),
                $equipo->{$columna},
            ),
        ])->save();

        return response()->json([
            'message' => 'Imagen actualizada.',
            'data' => new EquipoResource($equipo->loadCount('jugadores')),
        ]);
    }
}
