<?php

namespace App\Servicios\Pagos;

use App\Enumeraciones\EstadoOrden;
use App\Enumeraciones\EstadoPago;
use App\Excepciones\ReglaDeNegocio;
use App\Models\Inscripcion;
use App\Models\Orden;
use App\Models\Pago;
use App\Models\Torneo;
use App\Models\Usuario;
use App\Servicios\Carteras\GestorDeCarteras;
use App\Servicios\Inscripciones\GestorDeInscripciones;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Cobro de las inscripciones.
 *
 * Reglas que se cumplen aquí y de las que depende que no se pueda colar un
 * pago falso ni cobrar dos veces:
 *
 * - El monto sale siempre de la inscripción, nunca de la petición del cliente.
 * - Un equipo no acumula órdenes: mientras haya una vigente, se reutiliza.
 * - Toda notificación se procesa dentro de una transacción con la orden
 *   bloqueada, y el índice único (pasarela, referencia_externa) hace que la
 *   misma notificación repetida no vuelva a mover nada.
 */
class GestorDeCobros
{
    public function __construct(
        private readonly FabricaDePasarelas $pasarelas,
        private readonly GestorDeInscripciones $inscripciones,
        private readonly GestorDeCarteras $carteras,
    ) {}

    /**
     * Devuelve la orden vigente de una inscripción, o crea una nueva.
     */
    public function ordenPara(Inscripcion $inscripcion, Usuario $usuario): Orden
    {
        if ($inscripcion->estado->estaPagada()) {
            throw ReglaDeNegocio::porqueSi('Esta inscripción ya está pagada.');
        }

        if (! $inscripcion->esperaPago()) {
            throw ReglaDeNegocio::porqueSi('Esta inscripción no tiene ningún pago pendiente.');
        }

        return DB::transaction(function () use ($inscripcion, $usuario): Orden {
            $inscripcion = Inscripcion::query()->whereKey($inscripcion->getKey())->lockForUpdate()->firstOrFail();
            if (! $inscripcion->esperaPago()) {
                throw ReglaDeNegocio::porqueSi('Esta inscripción ya no tiene un pago pendiente.');
            }
            $pendientes = Orden::query()
                ->where('inscripcion_id', $inscripcion->getKey())
                ->pendientes()
                ->lockForUpdate()
                ->get();

            foreach ($pendientes as $orden) {
                if ($orden->admitePago()) {
                    return $orden;
                }

                $orden->forceFill(['estado' => EstadoOrden::Expirada])->save();
            }

            return $this->crearOrden($inscripcion, $usuario);
        });
    }

    public function iniciarCobro(Orden $orden): SesionDeCobro
    {
        if (! $orden->admitePago()) {
            throw ReglaDeNegocio::porqueSi('Esta orden ya no admite pagos. Genera una nueva desde tu panel.');
        }

        return $this->pasarelas->porNombre($orden->pasarela)->iniciarCobro($orden);
    }

    /**
     * Procesa una notificación ya verificada por la pasarela.
     */
    public function registrarNotificacion(NotificacionDePago $notificacion): Pago
    {
        return DB::transaction(function () use ($notificacion): Pago {
            $consultaOrden = Orden::query()->where('referencia', $notificacion->referenciaDeOrden)->first();
            if ($consultaOrden !== null) {
                Torneo::query()->whereKey($consultaOrden->inscripcion->torneo_id)->lockForUpdate()->firstOrFail();
            }
            $orden = Orden::query()
                ->where('referencia', $notificacion->referenciaDeOrden)
                ->lockForUpdate()
                ->first();

            if ($orden === null) {
                throw new ReglaDeNegocio('La orden de la notificación no existe.', 404);
            }

            $pagoPrevio = Pago::query()
                ->where('pasarela', $notificacion->pasarela)
                ->where('referencia_externa', $notificacion->referenciaExterna)
                ->first();

            // Idempotencia: la misma transacción notificada dos veces no vuelve
            // a mover la orden ni la inscripción.
            if ($pagoPrevio !== null) {
                return $pagoPrevio;
            }

            if ($notificacion->montoCentavos !== $orden->monto_centavos || $notificacion->moneda !== $orden->moneda) {
                Log::warning('Notificación de pago con monto distinto al de la orden.', [
                    'orden' => $orden->referencia,
                    'monto_orden' => $orden->monto_centavos,
                    'monto_notificado' => $notificacion->montoCentavos,
                ]);

                throw ReglaDeNegocio::porqueSi('El monto notificado no coincide con el de la orden.');
            }

            $pago = new Pago;
            $pago->forceFill([
                'orden_id' => $orden->getKey(),
                'pasarela' => $notificacion->pasarela,
                'referencia_externa' => $notificacion->referenciaExterna,
                'estado' => $notificacion->estado,
                'monto_centavos' => $notificacion->montoCentavos,
                'moneda' => $notificacion->moneda,
                'metodo' => $notificacion->metodo,
                'carga_util' => $notificacion->cargaUtil,
                'procesado_en' => now(),
            ])->save();

            if (! $orden->estaPendiente()) {
                return $pago;
            }

            if ($notificacion->estado === EstadoPago::Aprobado) {
                $orden->forceFill([
                    'estado' => EstadoOrden::Pagada,
                    'pagada_en' => now(),
                ])->save();

                $this->inscripciones->marcarComoPagada($orden->inscripcion);
            }

            if ($notificacion->estado === EstadoPago::Rechazado) {
                $orden->forceFill(['estado' => EstadoOrden::Fallida])->save();
            }

            return $pago;
        });
    }

    public function pagarConCartera(Inscripcion $inscripcion, Usuario $usuario): Orden
    {
        return DB::transaction(function () use ($inscripcion, $usuario): Orden {
            Torneo::query()->whereKey($inscripcion->torneo_id)->lockForUpdate()->firstOrFail();
            $inscripcion = Inscripcion::query()->whereKey($inscripcion->getKey())->lockForUpdate()->firstOrFail();
            if ($inscripcion->estado->estaPagada()) {
                return $inscripcion->ordenes()->where('estado', EstadoOrden::Pagada->value)->latest('id')->firstOrFail();
            }
            if (! $inscripcion->esperaPago()) {
                throw ReglaDeNegocio::porqueSi('Esta inscripción no admite pagos.');
            }
            $orden = $this->ordenPara($inscripcion, $usuario);
            $this->carteras->mover($usuario->getKey(), $inscripcion->moneda, -$inscripcion->monto_centavos, 'inscripcion',
                'Inscripción en '.$inscripcion->torneo->nombre, 'inscripcion:'.$inscripcion->ciclo, $inscripcion->torneo_id, $inscripcion->getKey());
            $this->inscripciones->marcarComoPagada($inscripcion);
            $orden->forceFill(['estado' => EstadoOrden::Pagada, 'pagada_en' => now()])->save();
            (new Pago)->forceFill([
                'orden_id' => $orden->getKey(), 'pasarela' => 'cartera', 'referencia_externa' => 'cartera:'.$inscripcion->ciclo,
                'estado' => EstadoPago::Aprobado, 'monto_centavos' => $inscripcion->monto_centavos, 'moneda' => $inscripcion->moneda,
                'metodo' => 'saldo_virtual', 'procesado_en' => now(),
            ])->save();

            return $orden;
        }, 3);
    }

    private function crearOrden(Inscripcion $inscripcion, Usuario $usuario): Orden
    {
        $orden = new Orden;

        $orden->forceFill([
            'referencia' => $this->referenciaUnica(),
            'inscripcion_id' => $inscripcion->getKey(),
            'usuario_id' => $usuario->getKey(),
            'monto_centavos' => $inscripcion->monto_centavos,
            'moneda' => $inscripcion->moneda,
            'estado' => EstadoOrden::Pendiente,
            'pasarela' => $this->pasarelas->activa()->identificador(),
            'expira_en' => now()->addMinutes((int) config('pagos.minutos_de_vigencia', 60)),
        ])->save();

        return $orden;
    }

    /**
     * Referencia pública de la orden: aleatoria, no secuencial, para que nadie
     * pueda adivinar la de otro equipo ni contar cuántas van.
     */
    private function referenciaUnica(): string
    {
        do {
            $referencia = 'NZ-'.Str::upper(Str::random(14));
        } while (Orden::query()->where('referencia', $referencia)->exists());

        return $referencia;
    }
}
