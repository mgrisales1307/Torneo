<?php

namespace App\Http\Controllers\Api\V1\Panel;

use App\Excepciones\ReglaDeNegocio;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrdenResource;
use App\Models\Inscripcion;
use App\Models\Orden;
use App\Servicios\Pagos\FabricaDePasarelas;
use App\Servicios\Pagos\GestorDeCobros;
use App\Servicios\Pagos\PasarelaSimulada;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pago de la inscripción por parte del capitán del equipo.
 */
class PagoController extends Controller
{
    public function __construct(
        private readonly GestorDeCobros $cobros,
        private readonly FabricaDePasarelas $pasarelas,
    ) {}

    /**
     * Abre (o recupera) la orden de pago de una inscripción y devuelve a dónde
     * tiene que ir el navegador para pagarla.
     */
    public function store(Inscripcion $inscripcion): JsonResponse
    {
        $this->authorize('pagar', $inscripcion);

        $orden = $this->cobros->ordenPara($inscripcion, request()->user());
        $sesion = $this->cobros->iniciarCobro($orden);

        return response()->json([
            'data' => new OrdenResource($orden->load('inscripcion.torneo', 'inscripcion.equipo')),
            'cobro' => $sesion->toArray(),
        ], 201);
    }

    public function show(Orden $orden): JsonResponse
    {
        $this->authorize('ver', $orden);

        return response()->json([
            'data' => new OrdenResource($orden->load(['inscripcion.torneo', 'inscripcion.equipo'])),
        ]);
    }

    public function cartera(Request $peticion, Inscripcion $inscripcion): JsonResponse
    {
        $this->authorize('pagar', $inscripcion);
        $orden = $this->cobros->pagarConCartera($inscripcion, $peticion->user());

        return response()->json(['message' => 'Inscripción pagada con tu saldo virtual.', 'data' => new OrdenResource($orden->load(['inscripcion.torneo', 'inscripcion.equipo']))]);
    }

    /**
     * Cierra el ciclo del pago en desarrollo.
     *
     * Esta ruta solo existe mientras la pasarela configurada sea la simulada
     * (ver routes/api.php). El resultado lo decide el servidor: el navegador
     * únicamente dice si quiere probar un pago aprobado o uno rechazado, y
     * nunca ve ni firma ni monto.
     */
    public function procesarConLaPasarelaSimulada(Request $peticion, Orden $orden): JsonResponse
    {
        $this->authorize('pagar', $orden);

        $datos = $peticion->validate([
            'resultado' => ['required', 'in:aprobado,rechazado'],
        ]);

        if (! $orden->admitePago()) {
            throw ReglaDeNegocio::porqueSi('Esta orden ya no admite pagos. Genera una nueva desde tu panel.');
        }

        $pasarela = $this->pasarelas->porNombre($orden->pasarela);

        if (! $pasarela instanceof PasarelaSimulada) {
            throw ReglaDeNegocio::porqueSi('Esta orden se paga con una pasarela real, no con la simulada.');
        }

        $pago = $this->cobros->registrarNotificacion(
            $pasarela->simularNotificacion($orden, $datos['resultado'] === 'aprobado')
        );

        $orden->refresh();

        return response()->json([
            'message' => $pago->fueAprobado()
                ? ($orden->inscripcion->torneo->flujo_automatico ? 'Pago simulado aprobado. Tu cupo está confirmado.' : 'Pago aprobado. Tu inscripción pasó a revisión de la organización.')
                : 'El pago fue rechazado. Puedes generar una nueva orden e intentarlo otra vez.',
            'data' => new OrdenResource($orden->load(['inscripcion.torneo', 'inscripcion.equipo'])),
        ]);
    }
}
