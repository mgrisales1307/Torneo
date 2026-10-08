<?php

namespace App\Http\Controllers\Api\V1\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\CrearInscripcionRequest;
use App\Http\Resources\InscripcionResource;
use App\Models\Equipo;
use App\Models\Inscripcion;
use App\Models\Torneo;
use App\Servicios\Equipos\GestorDeEquipos;
use App\Servicios\Inscripciones\GestorDeInscripciones;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Inscripción de mis equipos en los torneos. Solo el capitán inscribe, paga,
 * retira y hace el check-in.
 */
class InscripcionController extends Controller
{
    public function __construct(private readonly GestorDeInscripciones $gestor) {}

    /**
     * Las inscripciones de todos los equipos en los que juego. Las ven todos
     * los miembros; actuar sobre ellas, solo el capitán.
     */
    public function index(Request $peticion): AnonymousResourceCollection
    {
        $inscripciones = Inscripcion::query()
            ->whereIn('equipo_id', Equipo::query()->delMiembro($peticion->user())->select('id'))
            ->with(['torneo', 'equipo', 'ordenes' => fn ($consulta) => $consulta->latest()])
            ->latest()
            ->get();

        return InscripcionResource::collection($inscripciones);
    }

    public function store(CrearInscripcionRequest $peticion, Torneo $torneo, GestorDeEquipos $equipos): JsonResponse
    {
        /** @var Equipo $equipo */
        $equipo = $torneo->modalidad === 'individual'
            ? $equipos->individualPara($peticion->user(), $torneo->juego)
            : Equipo::query()->findOrFail($peticion->validated('equipo_id'));

        $this->authorize('inscribir', $equipo);
        $this->authorize('ver', $torneo);

        $inscripcion = $this->gestor->inscribir($torneo, $equipo, $peticion->user());

        return response()->json([
            'message' => $inscripcion->esperaPago()
                ? 'Inscripción creada. Paga para reservar el cupo.'
                : ($torneo->flujo_automatico ? 'Inscripción confirmada. Ya tienes tu cupo.' : 'Inscripción creada. La organización la revisará.'),
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

        $inscripcion = $this->gestor->retirar($inscripcion);

        return response()->json([
            'message' => 'El equipo quedó retirado del torneo.',
            'data' => new InscripcionResource($inscripcion->load(['torneo', 'equipo'])),
        ]);
    }

    public function checkIn(Inscripcion $inscripcion): JsonResponse
    {
        $this->authorize('hacerCheckIn', $inscripcion);

        $this->gestor->hacerCheckIn($inscripcion);

        return response()->json([
            'message' => 'Check-in hecho. Tu equipo está listo para el cuadro.',
            'data' => new InscripcionResource($inscripcion->load(['torneo', 'equipo'])),
        ]);
    }
}
