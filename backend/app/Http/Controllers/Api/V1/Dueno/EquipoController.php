<?php

namespace App\Http\Controllers\Api\V1\Dueno;

use App\Excepciones\ReglaDeNegocio;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dueno\GuardarEquipoRequest;
use App\Http\Resources\EquipoResource;
use App\Models\Equipo;
use App\Servicios\Medios\AlmacenDeImagenes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/**
 * Gestión de equipos por parte de su dueño.
 *
 * El listado solo trae los equipos propios, y cada acción sobre un equipo
 * concreto pasa por EquipoPolicy antes de tocar nada: cambiar el identificador
 * de la URL por el de otro equipo devuelve 403, no los datos ajenos.
 */
class EquipoController extends Controller
{
    public function __construct(private readonly AlmacenDeImagenes $imagenes) {}

    public function index(Request $peticion): AnonymousResourceCollection
    {
        $equipos = Equipo::query()
            ->delDueno($peticion->user())
            ->withCount('jugadores')
            ->with(['inscripciones' => fn ($consulta) => $consulta->with('torneo')])
            ->orderBy('nombre')
            ->get();

        return EquipoResource::collection($equipos);
    }

    public function store(GuardarEquipoRequest $peticion): JsonResponse
    {
        $this->authorize('crear', Equipo::class);

        $equipo = new Equipo($peticion->datosDelEquipo());
        $equipo->dueno_id = $peticion->user()->getKey();
        $equipo->save();

        return response()->json([
            'message' => 'Equipo creado. Ya puedes cargar su plantilla.',
            'data' => new EquipoResource($equipo->loadCount('jugadores')),
        ], 201);
    }

    public function show(Equipo $equipo): JsonResponse
    {
        $this->authorize('gestionarPlantilla', $equipo);

        $equipo->load([
            'jugadores' => fn ($consulta) => $consulta->orderBy('numero_camiseta')->orderBy('apellidos'),
            'inscripciones.torneo',
            'inscripciones.ordenes',
            'dueno',
        ])->loadCount('jugadores');

        return response()->json([
            'data' => new EquipoResource($equipo),
        ]);
    }

    public function update(GuardarEquipoRequest $peticion, Equipo $equipo): JsonResponse
    {
        $this->authorize('actualizar', $equipo);

        $equipo->fill($peticion->datosDelEquipo())->save();

        return response()->json([
            'message' => 'Equipo actualizado.',
            'data' => new EquipoResource($equipo->loadCount('jugadores')),
        ]);
    }

    public function destroy(Equipo $equipo): JsonResponse
    {
        $this->authorize('eliminar', $equipo);

        if ($equipo->inscripciones()->pagadas()->exists()) {
            throw ReglaDeNegocio::porqueSi(
                'Este equipo está inscrito en un torneo. Retíralo del torneo antes de eliminarlo.'
            );
        }

        $rutas = $equipo->rutasDeImagenes();

        DB::transaction(function () use ($equipo): void {
            $equipo->jugadores()->delete();
            $equipo->delete();
        });

        // Las imágenes se borran después de que la transacción cerró bien: si
        // la base fallara, no habríamos perdido los archivos.
        $this->imagenes->eliminarVarias($rutas);

        return response()->json(['message' => 'Equipo eliminado.']);
    }
}
