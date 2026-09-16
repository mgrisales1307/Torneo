<?php

namespace App\Enumeraciones;

/**
 * Estado de una transacción concreta contra la pasarela. Una orden puede
 * acumular varios pagos (un rechazo y luego uno aprobado).
 */
enum EstadoPago: string
{
    case Aprobado = 'aprobado';
    case Rechazado = 'rechazado';
    case Pendiente = 'pendiente';
    case Reembolsado = 'reembolsado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Aprobado => 'Aprobado',
            self::Rechazado => 'Rechazado',
            self::Pendiente => 'Pendiente',
            self::Reembolsado => 'Reembolsado',
        };
    }
}
