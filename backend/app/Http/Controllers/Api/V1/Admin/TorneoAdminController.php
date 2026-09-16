<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Excepciones\ReglaDeNegocio;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GuardarTorneoRequest;
use App\Http\Resources\TorneoResource;
use App\Models\Torneo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Gestión de torneos por parte de la organización. A diferencia del listado
 * público, aquí también se ven los borradores.
 */
class TorneoAdminController extends Controller
{
    public function index(Request $peticion): AnonymousResourceCollection
    {
        $this->authorize('administrar', Torneo::class);

        $torneos = Torneo::query()
            ->withCount(['inscripcionesQueOcupanCupo', 'partidos'])
            ->when($peticion->filled('estado'), fn ($consulta) => $consulta->where('estado', $peticion->string('estado')))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return TorneoResource::collection($torneos);
    }

    public function store(GuardarTorneoRequest $peticion): JsonResponse
    {
        $this->authorize('crear', Torneo::class);

        $torneo = new Torneo($peticion->validated());
        $torneo->creado_por_id = $peticion->user()->getKey();
        $torneo->save();

        return response()->json([
            'message' => 'Torneo creado.',
            'data' => new TorneoResource($torneo->loadCount(['inscripcionesQueOcupanCupo', 'partidos'])),
        ], 201);
    }

    public function show(Torneo $torneo): JsonResponse
    {
        $this->authorize('administrar', Torneo::class);

        return response()->json([
            'data' => new TorneoResource(
                $torneo->loadCount(['inscripcionesQueOcupanCupo', 'partidos'])->load('creador')
            ),
        ]);
    }

    public function update(GuardarTorneoRequest $peticion, Torneo $torneo): JsonResponse
    {
        $this->authorize('actualizar', $torneo);

        $torneo->fill($peticion->validated())->save();

        return response()->json([
            'message' => 'Torneo actualizado.',
            'data' => new TorneoResource($torneo->loadCount(['inscripcionesQueOcupanCupo', 'partidos'])),
        ]);
    }

    public function destroy(Torneo $torneo): JsonResponse
    {
        $this->authorize('eliminar', $torneo);

        if ($torneo->inscripciones()->pagadas()->exists()) {
            throw ReglaDeNegocio::porqueSi(
                'Hay equipos que ya pagaron su inscripción en este torneo: no se puede eliminar.'
            );
        }

        $torneo->delete();

        return response()->json(['message' => 'Torneo eliminado.']);
    }
}
