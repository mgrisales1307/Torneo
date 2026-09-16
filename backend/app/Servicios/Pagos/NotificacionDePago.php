<?php

namespace App\Servicios\Pagos;

use App\Enumeraciones\EstadoPago;

/**
 * Notificación ya interpretada y verificada de la pasarela, traducida a los
 * términos del proyecto. A partir de aquí, al resto del sistema le da igual
 * qué proveedor la envió.
 */
final readonly class NotificacionDePago
{
    /**
     * @param  array<string, mixed>  $cargaUtil
     */
    public function __construct(
        public string $pasarela,
        public string $referenciaDeOrden,
        public string $referenciaExterna,
        public EstadoPago $estado,
        public int $montoCentavos,
        public string $moneda,
        public ?string $metodo = null,
        public array $cargaUtil = [],
    ) {}
}
