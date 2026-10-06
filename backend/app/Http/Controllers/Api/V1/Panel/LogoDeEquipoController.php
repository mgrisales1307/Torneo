<?php

namespace App\Http\Controllers\Api\V1\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\SubirLogoRequest;
use App\Http\Resources\EquipoResource;
use App\Models\Equipo;
use App\Servicios\Medios\AlmacenDeImagenes;
use Illuminate\Http\JsonResponse;

class LogoDeEquipoController extends Controller
{
    public function __construct(private readonly AlmacenDeImagenes $imagenes) {}

    public function store(SubirLogoRequest $peticion, Equipo $equipo): JsonResponse
    {
        $this->authorize('actualizar', $equipo);

        $equipo->forceFill([
            'logo_ruta' => $this->imagenes->guardar($peticion->file('logo'), 'logo', $equipo->logo_ruta),
        ])->save();

        return response()->json([
            'message' => 'Logo actualizado.',
            'data' => new EquipoResource($equipo->load('miembros')->loadCount('miembros')),
        ]);
    }
}
