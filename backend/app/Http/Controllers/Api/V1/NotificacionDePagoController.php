<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Servicios\Pagos\FabricaDePasarelas;
use App\Servicios\Pagos\GestorDeCobros;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Entrada de las notificaciones de pago (webhook).
 *
 * Es la única ruta de escritura sin sesión, y por eso lo primero que hace la
 * pasarela correspondiente es verificar la firma del mensaje. Si no cuadra, se
 * responde 403 y no se toca ninguna orden. Procesar dos veces la misma
 * notificación es inofensivo: el gestor de cobros es idempotente.
 */
class NotificacionDePagoController extends Controller
{
    public function __invoke(
        Request $peticion,
        string $pasarela,
        FabricaDePasarelas $pasarelas,
        GestorDeCobros $cobros,
    ): JsonResponse {
        $notificacion = $pasarelas->porNombre($pasarela)->interpretarNotificacion($peticion);

        $pago = $cobros->registrarNotificacion($notificacion);

        return response()->json([
            'message' => 'Notificación procesada.',
            'estado' => $pago->estado->value,
        ]);
    }
}
