<?php

namespace App\Servicios\Pagos;

use App\Excepciones\FirmaDePagoInvalida;
use App\Models\Orden;
use Illuminate\Http\Request;

/**
 * Contrato que cumple cualquier pasarela de pagos.
 *
 * Todo lo que el proyecto necesita de un proveedor de cobro son estas tres
 * cosas. Integrar Wompi, Mercado Pago, Stripe o PayU es escribir una clase que
 * implemente esta interfaz y registrarla en config/pagos.php: ni los
 * controladores, ni los modelos, ni el frontend cambian.
 */
interface PasarelaDePagos
{
    /**
     * Nombre con el que la pasarela queda registrada en la base de datos.
     */
    public function identificador(): string;

    /**
     * Abre el cobro de una orden y devuelve a dónde debe ir el navegador.
     */
    public function iniciarCobro(Orden $orden): SesionDeCobro;

    /**
     * Verifica la firma de una notificación entrante y la traduce al lenguaje
     * del proyecto.
     *
     * @throws FirmaDePagoInvalida cuando la firma falta, no
     *                             coincide o llegó fuera de la ventana de tolerancia.
     */
    public function interpretarNotificacion(Request $peticion): NotificacionDePago;
}
