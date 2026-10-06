<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enumeraciones\EstadoInscripcion;
use App\Enumeraciones\EstadoOrden;
use App\Enumeraciones\EstadoPartido;
use App\Enumeraciones\EstadoTorneo;
use App\Http\Controllers\Controller;
use App\Http\Resources\InscripcionResource;
use App\Http\Resources\PartidoResource;
use App\Http\Resources\TorneoResource;
use App\Models\Equipo;
use App\Models\Inscripcion;
use App\Models\Orden;
use App\Models\Partido;
use App\Models\Torneo;
use App\Models\Usuario;
use App\Soporte\Dinero;
use Illuminate\Http\JsonResponse;

/**
 * Cifras y pendientes del panel de la organización.
 */
class ResumenAdminController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $this->authorize('administrar', Torneo::class);

        $recaudado = (int) Orden::query()
            ->where('estado', EstadoOrden::Pagada->value)
            ->sum('monto_centavos');

        $porRevisar = Inscripcion::query()->where('estado', EstadoInscripcion::PendienteRevision->value);
        $disputas = Partido::query()->where('estado', EstadoPartido::EnDisputa->value);

        return response()->json([
            'cifras' => [
                'torneos_activos' => Torneo::query()->whereIn('estado', [
                    EstadoTorneo::InscripcionesAbiertas->value,
                    EstadoTorneo::InscripcionesCerradas->value,
                    EstadoTorneo::EnCurso->value,
                ])->count(),
                'equipos' => Equipo::query()->count(),
                'jugadores' => Usuario::query()->count(),
                'inscripciones_por_revisar' => (clone $porRevisar)->count(),
                'disputas' => (clone $disputas)->count(),
                'recaudado_centavos' => $recaudado,
                'recaudado_formateado' => Dinero::formatear($recaudado),
            ],

            'por_revisar' => InscripcionResource::collection(
                $porRevisar->with(['torneo', 'equipo'])->latest('pagada_en')->limit(8)->get()
            ),

            'disputas' => PartidoResource::collection(
                $disputas->with(['equipoA', 'equipoB', 'torneo'])->orderBy('updated_at')->limit(8)->get()
            ),

            // Torneos listos para arrancar: publicados, sin cuadro todavía.
            'por_iniciar' => TorneoResource::collection(
                Torneo::query()
                    ->whereIn('estado', [EstadoTorneo::InscripcionesAbiertas->value, EstadoTorneo::InscripcionesCerradas->value])
                    ->withCount(['inscripcionesQueOcupanCupo', 'partidos'])
                    ->orderByRaw('inicia_en IS NULL, inicia_en')
                    ->limit(6)
                    ->get()
            ),
        ]);
    }
}
