<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enumeraciones\EstadoInscripcion;
use App\Enumeraciones\EstadoOrden;
use App\Enumeraciones\EstadoTorneo;
use App\Http\Controllers\Controller;
use App\Http\Resources\InscripcionResource;
use App\Http\Resources\PartidoResource;
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
        $recaudado = (int) Orden::query()
            ->where('estado', EstadoOrden::Pagada->value)
            ->sum('monto_centavos');

        return response()->json([
            'cifras' => [
                'torneos_activos' => Torneo::query()->whereIn('estado', [
                    EstadoTorneo::InscripcionesAbiertas->value,
                    EstadoTorneo::InscripcionesCerradas->value,
                    EstadoTorneo::EnCurso->value,
                ])->count(),
                'equipos' => Equipo::query()->count(),
                'usuarios' => Usuario::query()->count(),
                'inscripciones_por_revisar' => Inscripcion::query()
                    ->where('estado', EstadoInscripcion::PendienteRevision->value)
                    ->count(),
                'partidos_sin_resultado' => Partido::query()
                    ->whereNull('goles_local')
                    ->whereNotNull('programado_para')
                    ->where('programado_para', '<', now())
                    ->count(),
                'recaudado_centavos' => $recaudado,
                'recaudado_formateado' => Dinero::formatear($recaudado),
            ],

            'por_revisar' => InscripcionResource::collection(
                Inscripcion::query()
                    ->where('estado', EstadoInscripcion::PendienteRevision->value)
                    ->with(['torneo', 'equipo'])
                    ->latest('pagada_en')
                    ->limit(8)
                    ->get()
            ),

            'partidos_pendientes' => PartidoResource::collection(
                Partido::query()
                    ->whereNull('goles_local')
                    ->whereNotNull('programado_para')
                    ->where('programado_para', '<', now())
                    ->with(['equipoLocal', 'equipoVisitante', 'torneo'])
                    ->orderBy('programado_para')
                    ->limit(8)
                    ->get()
            ),
        ]);
    }
}
