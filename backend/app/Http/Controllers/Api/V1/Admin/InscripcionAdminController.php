<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enumeraciones\EstadoInscripcion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RevisarInscripcionRequest;
use App\Http\Resources\InscripcionResource;
use App\Models\Inscripcion;
use App\Models\Torneo;
use App\Servicios\Inscripciones\GestorDeInscripciones;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Revisión de inscripciones: la organización decide qué equipos entran.
 */
class InscripcionAdminController extends Controller
{
    public function __construct(private readonly GestorDeInscripciones $gestor) {}

    public function index(Request $peticion): AnonymousResourceCollection
    {
        $this->authorize('administrar', Torneo::class);

        $inscripciones = Inscripcion::query()
            ->with([
                'torneo',
                'revisor',
                'equipo' => fn ($consulta) => $consulta->withCount('miembros'),
                'equipo.miembros',
            ])
            ->when(
                $peticion->filled('estado') && EstadoInscripcion::tryFrom((string) $peticion->string('estado')) !== null,
                fn ($consulta) => $consulta->where('estado', $peticion->string('estado')),
            )
            ->when($peticion->filled('torneo'), fn ($consulta) => $consulta->where('torneo_id', $peticion->integer('torneo')))
            ->orderByRaw("CASE estado WHEN 'pendiente_revision' THEN 0 WHEN 'pendiente_pago' THEN 1 ELSE 2 END")
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return InscripcionResource::collection($inscripciones);
    }

    public function aprobar(RevisarInscripcionRequest $peticion, Inscripcion $inscripcion): JsonResponse
    {
        $this->authorize('revisar', $inscripcion);

        $this->gestor->aprobar($inscripcion, $peticion->user(), $peticion->validated('notas'));

        return response()->json([
            'message' => 'Inscripción aprobada.',
            'data' => new InscripcionResource($inscripcion->load(['torneo', 'equipo', 'revisor'])),
        ]);
    }

    public function rechazar(RevisarInscripcionRequest $peticion, Inscripcion $inscripcion): JsonResponse
    {
        $this->authorize('revisar', $inscripcion);

        $inscripcion = $this->gestor->rechazar($inscripcion, $peticion->user(), (string) $peticion->validated('notas'));

        return response()->json([
            'message' => 'Inscripción rechazada.',
            'data' => new InscripcionResource($inscripcion->load(['torneo', 'equipo', 'revisor'])),
        ]);
    }
}
