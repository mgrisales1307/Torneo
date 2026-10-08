<?php

namespace App\Http\Controllers\Api\V1\Panel;

use App\Enumeraciones\EstadoTorneo;
use App\Excepciones\ReglaDeNegocio;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\GuardarEquipoRequest;
use App\Http\Resources\EquipoResource;
use App\Models\Equipo;
use App\Servicios\Equipos\GestorDeEquipos;
use App\Servicios\Medios\AlmacenDeImagenes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/**
 * Mis equipos: los que capitaneo y en los que juego.
 *
 * Cada acción sobre un equipo concreto pasa por EquipoPolicy antes de tocar
 * nada: cambiar el identificador de la URL por el de otro equipo devuelve 403,
 * no los datos ajenos.
 */
class EquipoController extends Controller
{
    public function __construct(
        private readonly GestorDeEquipos $gestor,
        private readonly AlmacenDeImagenes $imagenes,
    ) {}

    public function index(Request $peticion): AnonymousResourceCollection
    {
        $equipos = Equipo::query()
            ->delMiembro($peticion->user())
            ->where('es_individual', false)
            ->with(['miembros', 'inscripciones' => fn ($consulta) => $consulta->vigentes()->with('torneo')])
            ->withCount('miembros')
            ->orderBy('juego')
            ->orderBy('nombre')
            ->get();

        return EquipoResource::collection($equipos);
    }

    public function store(GuardarEquipoRequest $peticion): JsonResponse
    {
        $this->authorize('crear', Equipo::class);

        $equipo = $this->gestor->crear($peticion->user(), $peticion->validated());

        return response()->json([
            'message' => 'Equipo creado. Comparte el código para que se unan los demás.',
            'data' => new EquipoResource($equipo->load('miembros')->loadCount('miembros')),
        ], 201);
    }

    public function show(Equipo $equipo): JsonResponse
    {
        $this->authorize('verComoMiembro', $equipo);

        $equipo->load([
            'miembros.cuentasDeJuego',
            'inscripciones' => fn ($consulta) => $consulta->latest()->with(['torneo', 'ordenes' => fn ($ordenes) => $ordenes->latest()]),
        ])->loadCount('miembros');

        return response()->json([
            'data' => new EquipoResource($equipo),
        ]);
    }

    public function update(GuardarEquipoRequest $peticion, Equipo $equipo): JsonResponse
    {
        $this->authorize('actualizar', $equipo);

        $equipo->fill($peticion->validated())->save();

        return response()->json([
            'message' => 'Equipo actualizado.',
            'data' => new EquipoResource($equipo->load('miembros')->loadCount('miembros')),
        ]);
    }

    public function destroy(Equipo $equipo): JsonResponse
    {
        $this->authorize('eliminar', $equipo);

        if ($equipo->inscripciones()->pagadas()->whereHas('torneo', fn ($torneo) => $torneo->whereNotIn('estado', [EstadoTorneo::Finalizado->value, EstadoTorneo::Cancelado->value]))->exists()) {
            throw ReglaDeNegocio::porqueSi(
                'Este equipo está inscrito en un torneo. Retíralo del torneo antes de eliminarlo.'
            );
        }

        $rutas = $equipo->rutasDeImagenes();

        DB::transaction(function () use ($equipo): void {
            $equipo->miembros()->detach();
            $equipo->delete();
        });

        // Las imágenes se borran después de que la transacción cerró bien: si
        // la base fallara, no habríamos perdido los archivos.
        $this->imagenes->eliminarVarias($rutas);

        return response()->json(['message' => 'Equipo eliminado.']);
    }

    /**
     * Cambia el código de invitación: el anterior deja de servir.
     */
    public function nuevoCodigo(Equipo $equipo): JsonResponse
    {
        $this->authorize('gestionarPlantilla', $equipo);

        $this->gestor->nuevoCodigo($equipo);

        return response()->json([
            'message' => 'Código nuevo listo. El anterior ya no sirve.',
            'data' => new EquipoResource($equipo->load('miembros')->loadCount('miembros')),
        ]);
    }
}
