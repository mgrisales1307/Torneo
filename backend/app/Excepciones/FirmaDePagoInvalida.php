<?php

namespace App\Excepciones;

/**
 * La notificación de pago no venía firmada por la pasarela, o la firma no
 * coincide. Se responde 403 y no se toca ninguna orden.
 */
class FirmaDePagoInvalida extends ReglaDeNegocio
{
    public function __construct(string $mensaje = 'La notificación de pago no tiene una firma válida.')
    {
        parent::__construct($mensaje, 403);
    }
}
