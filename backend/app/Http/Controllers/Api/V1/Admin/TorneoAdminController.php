<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enumeraciones\EstadoTorneo;
use App\Excepciones\ReglaDeNegocio;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GuardarTorneoRequest;
use App\Http\Resources\TorneoResource;
use App\Models\Torneo;
use App\Servicios\Torneos\InicioDeTorneo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Gestión de torneos por parte de la organización. A diferencia del listado
 * público, aquí también se ven los borradores.
 */
class TorneoAdminController extends Controller
{
    /**
     * Pasos que la organización puede dar a mano. «En curso» no está: se llega
     * iniciando el torneo, que arma el cuadro. «Finalizado» tampoco: llega
     * solo cuando se juega el último partido.
     */
    private const TRANSICIONES = [
        'borrador' => ['inscripciones_abiertas', 'cancelado'],
        'inscripciones_abiertas' => ['inscripciones_cerradas', 'borrador', 'cancelado'],
        'inscripciones_cerradas' => ['inscripciones_abiertas', 'cancelado'],
        'en_curso' => ['cancelado'],
        'finalizado' => [],
        'cancelado' => [],
    ];

    /**
     * Ajustes que ya no se tocan una vez armado el cuadro.
     */
    private const ESTRUCTURALES = [
        'juego', 'formato', 'cupo_maximo_equipos', 'mejor_de', 'mejor_de_final', 'cantidad_de_grupos',
        'clasifican_por_grupo', 'rondas_suizo', 'partidas_battle_royale', 'costo_inscripcion_centavos', 'moneda',
    ];

    public function __construct(private readonly InicioDeTorneo $inicio) {}

    public function index(Request $peticion): AnonymousResourceCollection
    {
        $this->authorize('administrar', Torneo::class);

        $torneos = Torneo::query()
            ->withCount(['inscripcionesQueOcupanCupo', 'partidos'])
            ->when($peticion->filled('estado'), fn ($consulta) => $consulta->where('estado', $peticion->string('estado')))
            ->when($peticion->filled('juego'), fn ($consulta) => $consulta->where('juego', $peticion->string('juego')))
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
            'message' => 'Torneo creado como borrador. Publícalo para abrir las inscripciones.',
            'data' => new TorneoResource($torneo->loadCount(['inscripcionesQueOcupanCupo', 'partidos'])),
        ], 201);
    }

    public function show(Torneo $torneo): JsonResponse
    {
        $this->authorize('administrar', Torneo::class);

        return response()->json([
            'data' => new TorneoResource($torneo->load('campeon')->loadCount(['inscripcionesQueOcupanCupo', 'partidos'])),
            'transiciones' => self::TRANSICIONES[$torneo->estado->value],
        ]);
    }

    public function update(GuardarTorneoRequest $peticion, Torneo $torneo): JsonResponse
    {
        $this->authorize('actualizar', $torneo);

        $datos = $peticion->validated();

        if ($torneo->estado->haEmpezado()) {
            $cambiados = array_filter(
                array_intersect_key($datos, array_flip(self::ESTRUCTURALES)),
                fn ($valor, string $campo): bool => $this->valorComparable($torneo->{$campo}) != $valor,
                ARRAY_FILTER_USE_BOTH,
            );

            if ($cambiados !== []) {
                throw ReglaDeNegocio::porqueSi(
                    'El torneo ya empezó: el juego, el formato, el cupo y el costo no se pueden cambiar.'
                );
            }
        }

        $torneo->fill($datos)->save();

        return response()->json([
            'message' => 'Torneo actualizado.',
            'data' => new TorneoResource($torneo->loadCount(['inscripcionesQueOcupanCupo', 'partidos'])),
        ]);
    }

    public function cambiarEstado(Request $peticion, Torneo $torneo): JsonResponse
    {
        $this->authorize('actualizar', $torneo);

        $permitidos = self::TRANSICIONES[$torneo->estado->value];

        $datos = $peticion->validate([
            'estado' => ['required', Rule::in($permitidos)],
        ], [
            'estado.in' => $permitidos === []
                ? 'Este torneo ya está cerrado.'
                : 'Desde «'.$torneo->estado->etiqueta().'» no se puede pasar a ese estado.',
        ]);

        $nuevo = EstadoTorneo::from($datos['estado']);

        if ($nuevo === EstadoTorneo::Borrador && $torneo->inscripciones()->exists()) {
            throw ReglaDeNegocio::porqueSi('Ya hay equipos inscritos: el torneo no puede volver a borrador.');
        }

        $torneo->forceFill(['estado' => $nuevo])->save();

        return response()->json([
            'message' => match ($nuevo) {
                EstadoTorneo::InscripcionesAbiertas => 'Torneo publicado con las inscripciones abiertas.',
                EstadoTorneo::InscripcionesCerradas => 'Inscripciones cerradas.',
                EstadoTorneo::Cancelado => 'Torneo cancelado.',
                default => 'Estado actualizado.',
            },
            'data' => new TorneoResource($torneo->loadCount(['inscripcionesQueOcupanCupo', 'partidos'])),
        ]);
    }

    /**
     * Arma el cuadro con los equipos aprobados (y con check-in) y arranca.
     */
    public function iniciar(Torneo $torneo): JsonResponse
    {
        $this->authorize('iniciar', $torneo);

        $torneo = $this->inicio->iniciar($torneo);

        return response()->json([
            'message' => 'Torneo iniciado. El cuadro ya está armado.',
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

    private function valorComparable(mixed $valor): mixed
    {
        return $valor instanceof \BackedEnum ? $valor->value : $valor;
    }
}
