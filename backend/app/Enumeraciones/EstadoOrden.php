<?php

namespace App\Enumeraciones;

enum EstadoOrden: string
{
    case Pendiente = 'pendiente';
    case Pagada = 'pagada';
    case Fallida = 'fallida';
    case Expirada = 'expirada';
    case Cancelada = 'cancelada';
    case Reembolsada = 'reembolsada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente de pago',
            self::Pagada => 'Pagada',
            self::Fallida => 'Fallida',
            self::Expirada => 'Expirada',
            self::Cancelada => 'Cancelada',
            self::Reembolsada => 'Reembolsada',
        };
    }

    /**
     * Una orden en estado final no vuelve a cambiar: el webhook que llegue
     * tarde se descarta en vez de reabrirla.
     */
    public function esFinal(): bool
    {
        return $this !== self::Pendiente;
    }
}
