<?php

namespace App\Http\Controllers\Api\V1\Dueno;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dueno\CrearInscripcionRequest;
use App\Http\Resources\InscripcionResource;
use App\Models\Equipo;
use App\Models\Inscripcion;
use App\Models\Torneo;
use App\Servicios\Inscripciones\GestorDeInscripciones;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Inscripción de mis equipos en los torneos.
 */
class InscripcionController extends Controller
{
    public function __construct(private readonly GestorDeInscripciones $gestor) {}

    public function index(Request $peticion): AnonymousResourceCollection
    {
        $inscripciones = Inscripcion::query()
            ->whereIn('equipo_id', Equipo::query()->delDueno($peticion->user())->select('id'))
            ->with(['torneo', 'equipo', 'ordenes' => fn ($consulta) => $consulta->latest()])
            ->latest()
            ->get();

        return InscripcionResource::collection($inscripciones);
    }

    public function store(CrearInscripcionRequest $peticion, Torneo $torneo): JsonResponse
    {
        /** @var Equipo $equipo */
        $equipo = Equipo::query()->findOrFail($peticion->validated('equipo_id'));

        // Dos permisos distintos: que el equipo sea mío, y que el torneo admita
        // inscripciones ahora mismo.
        $this->authorize('inscribir', $equipo);
        $this->authorize('inscribirEquipo', $torneo);

        $inscripcion = $this->gestor->inscribir($torneo, $equipo);

        return response()->json([
            'message' => $inscripcion->esperaPago()
                ? 'Inscripción creada. Queda pendiente el pago para reservar el cupo.'
                : 'Inscripción creada. Queda a la espera de que la organización la revise.',
            'data' => new InscripcionResource($inscripcion->load(['torneo', 'equipo'])),
        ], 201);
    }

    public function show(Inscripcion $inscripcion): JsonResponse
    {
        $this->authorize('ver', $inscripcion);

        return response()->json([
            'data' => new InscripcionResource(
                $inscripcion->load(['torneo', 'equipo', 'ordenes' => fn ($consulta) => $consulta->latest()])
            ),
        ]);
    }

    /**
     * Retira al equipo del torneo.
     */
    public function destroy(Inscripcion $inscripcion): JsonResponse
    {
        $this->authorize('retirar', $inscripcion);

        $this->gestor->retirar($inscripcion);

        return response()->json([
            'message' => 'El equipo quedó retirado del torneo.',
            'data' => new InscripcionResource($inscripcion->load(['torneo', 'equipo'])),
        ]);
    }
}
