<?php

namespace App\Http\Controllers\Api\V1\Dueno;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dueno\GuardarJugadorRequest;
use App\Http\Resources\JugadorPrivadoResource;
use App\Models\Equipo;
use App\Models\Jugador;
use App\Servicios\Medios\AlmacenDeImagenes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/**
 * Plantilla del equipo: alta, edición y baja de jugadores.
 */
class JugadorController extends Controller
{
    public function __construct(private readonly AlmacenDeImagenes $imagenes) {}

    public function index(Equipo $equipo): AnonymousResourceCollection
    {
        $this->authorize('gestionarPlantilla', $equipo);

        $jugadores = $equipo->jugadores()
            ->orderBy('numero_camiseta')
            ->orderBy('apellidos')
            ->get();

        return JugadorPrivadoResource::collection($jugadores);
    }

    public function store(GuardarJugadorRequest $peticion, Equipo $equipo): JsonResponse
    {
        $this->authorize('gestionarPlantilla', $equipo);

        $jugador = DB::transaction(function () use ($peticion, $equipo): Jugador {
            $jugador = new Jugador($peticion->safe()->except('es_capitan'));
            $jugador->equipo_id = $equipo->getKey();
            $jugador->es_capitan = $peticion->boolean('es_capitan');
            $jugador->save();

            $this->dejarUnSoloCapitan($equipo, $jugador);

            return $jugador;
        });

        return response()->json([
            'message' => $jugador->nombreCompleto().' se sumó a la plantilla.',
            'data' => new JugadorPrivadoResource($jugador),
        ], 201);
    }

    public function update(GuardarJugadorRequest $peticion, Jugador $jugador): JsonResponse
    {
        $this->authorize('actualizar', $jugador);

        DB::transaction(function () use ($peticion, $jugador): void {
            $jugador->fill($peticion->safe()->except('es_capitan'));

            if ($peticion->has('es_capitan')) {
                $jugador->es_capitan = $peticion->boolean('es_capitan');
            }

            $jugador->save();

            $this->dejarUnSoloCapitan($jugador->equipo, $jugador);
        });

        return response()->json([
            'message' => 'Jugador actualizado.',
            'data' => new JugadorPrivadoResource($jugador->refresh()),
        ]);
    }

    /**
     * Da de baja al jugador. Es baja lógica: su historial de goles y tarjetas
     * sigue contando en los torneos que ya jugó.
     */
    public function destroy(Jugador $jugador): JsonResponse
    {
        $this->authorize('eliminar', $jugador);

        $nombre = $jugador->nombreCompleto();
        $foto = $jugador->foto_ruta;

        $jugador->delete();
        $this->imagenes->eliminar($foto);

        return response()->json([
            'message' => $nombre.' salió de la plantilla.',
        ]);
    }

    /**
     * Un equipo tiene un capitán. Al marcar uno nuevo, el anterior deja de
     * serlo, en lugar de dejar dos brazaletes en la misma cancha.
     */
    private function dejarUnSoloCapitan(Equipo $equipo, Jugador $jugador): void
    {
        if (! $jugador->es_capitan) {
            return;
        }

        $equipo->jugadores()
            ->whereKeyNot($jugador->getKey())
            ->where('es_capitan', true)
            ->update(['es_capitan' => false]);
    }
}
