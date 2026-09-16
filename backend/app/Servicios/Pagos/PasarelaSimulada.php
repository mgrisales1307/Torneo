<?php

namespace App\Servicios\Pagos;

use App\Enumeraciones\EstadoPago;
use App\Excepciones\FirmaDePagoInvalida;
use App\Models\Orden;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Pasarela de desarrollo. Recorre el mismo camino que un proveedor real —
 * abrir un cobro, notificar el resultado firmado, verificar esa firma — pero
 * sin salir del proyecto, así que el flujo de pago se puede probar y demostrar
 * sin credenciales de nadie.
 *
 * La firma es un HMAC-SHA256 sobre «marca de tiempo + cuerpo», comparado en
 * tiempo constante, con una ventana de tolerancia para que una notificación
 * capturada no se pueda reenviar más tarde. Es exactamente el esquema que usan
 * Stripe y Wompi, de modo que cambiar de proveedor no cambia el diseño.
 */
class PasarelaSimulada implements PasarelaDePagos
{
    public const CABECERA_FIRMA = 'X-Pago-Firma';

    public const CABECERA_MARCA_DE_TIEMPO = 'X-Pago-Marca-De-Tiempo';

    public function identificador(): string
    {
        return 'simulada';
    }

    public function iniciarCobro(Orden $orden): SesionDeCobro
    {
        return new SesionDeCobro(
            pasarela: $this->identificador(),
            referencia: $orden->referencia,
            // Pantalla de pago dentro de la SPA. Un proveedor real devolvería
            // aquí la URL de su propio formulario.
            url: '/panel/pagos/'.$orden->referencia,
            expiraEn: $orden->expira_en,
            datos: [
                'modo' => 'sandbox',
                'monto_centavos' => $orden->monto_centavos,
                'moneda' => $orden->moneda,
            ],
        );
    }

    public function interpretarNotificacion(Request $peticion): NotificacionDePago
    {
        $this->verificarFirma($peticion);

        $datos = $peticion->json()->all();

        foreach (['referencia', 'referencia_externa', 'estado', 'monto_centavos', 'moneda'] as $campo) {
            if (! isset($datos[$campo])) {
                throw new FirmaDePagoInvalida("La notificación de pago no trae el campo «$campo».");
            }
        }

        $estado = EstadoPago::tryFrom((string) $datos['estado']);

        if ($estado === null) {
            throw new FirmaDePagoInvalida('La notificación de pago trae un estado desconocido.');
        }

        return new NotificacionDePago(
            pasarela: $this->identificador(),
            referenciaDeOrden: (string) $datos['referencia'],
            referenciaExterna: (string) $datos['referencia_externa'],
            estado: $estado,
            montoCentavos: (int) $datos['monto_centavos'],
            moneda: (string) $datos['moneda'],
            metodo: isset($datos['metodo']) ? (string) $datos['metodo'] : null,
            cargaUtil: $datos,
        );
    }

    /**
     * Hace de proveedor: arma la notificación del resultado, la firma y la
     * vuelve a leer por el mismo camino que una notificación de internet.
     *
     * Existe para que el entorno de desarrollo pueda cerrar el ciclo del pago
     * sin exponerle al navegador ninguna firma ni ningún monto: el resultado
     * lo decide el servidor a partir de la orden.
     */
    public function simularNotificacion(Orden $orden, bool $aprobar, ?string $metodo = null): NotificacionDePago
    {
        $cuerpo = [
            'referencia' => $orden->referencia,
            'referencia_externa' => 'SIM-'.Str::upper(Str::random(18)),
            'estado' => ($aprobar ? EstadoPago::Aprobado : EstadoPago::Rechazado)->value,
            'monto_centavos' => $orden->monto_centavos,
            'moneda' => $orden->moneda,
            'metodo' => $metodo ?? 'tarjeta_de_prueba',
        ];

        $json = json_encode($cuerpo, JSON_THROW_ON_ERROR);
        $marcaDeTiempo = (string) now()->getTimestamp();

        $peticion = Request::create(
            uri: '/api/v1/pagos/notificaciones/'.$this->identificador(),
            method: 'POST',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_'.Str::upper(str_replace('-', '_', self::CABECERA_FIRMA)) => $this->firmar($marcaDeTiempo, $json),
                'HTTP_'.Str::upper(str_replace('-', '_', self::CABECERA_MARCA_DE_TIEMPO)) => $marcaDeTiempo,
            ],
            content: $json,
        );

        return $this->interpretarNotificacion($peticion);
    }

    public function firmar(string $marcaDeTiempo, string $cuerpo): string
    {
        return hash_hmac('sha256', $marcaDeTiempo.'.'.$cuerpo, $this->secreto());
    }

    private function verificarFirma(Request $peticion): void
    {
        $firma = (string) $peticion->header(self::CABECERA_FIRMA, '');
        $marcaDeTiempo = (string) $peticion->header(self::CABECERA_MARCA_DE_TIEMPO, '');

        if ($firma === '' || $marcaDeTiempo === '' || ! ctype_digit($marcaDeTiempo)) {
            throw new FirmaDePagoInvalida;
        }

        $tolerancia = (int) config('pagos.pasarelas.simulada.tolerancia_en_segundos', 300);

        if (abs(now()->getTimestamp() - (int) $marcaDeTiempo) > $tolerancia) {
            throw new FirmaDePagoInvalida('La notificación de pago llegó fuera de la ventana de tiempo aceptada.');
        }

        $esperada = $this->firmar($marcaDeTiempo, $peticion->getContent());

        // hash_equals compara en tiempo constante: una comparación normal
        // filtraría, por el tiempo que tarda, cuántos caracteres acertó quien
        // esté intentando adivinar la firma.
        if (! hash_equals($esperada, $firma)) {
            throw new FirmaDePagoInvalida;
        }
    }

    private function secreto(): string
    {
        $secreto = (string) config('pagos.pasarelas.simulada.secreto', '');

        if ($secreto === '') {
            throw new RuntimeException(
                'Falta el secreto de la pasarela de pagos. Define PASARELA_SIMULADA_SECRETO o APP_KEY.'
            );
        }

        return $secreto;
    }
}
