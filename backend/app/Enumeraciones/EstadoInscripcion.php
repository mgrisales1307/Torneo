<?php

namespace App\Enumeraciones;

/**
 * Recorrido de una inscripción: el dueño la crea, paga, la organización la
 * revisa y queda aprobada. Solo las aprobadas entran al calendario.
 */
enum EstadoInscripcion: string
{
    case PendientePago = 'pendiente_pago';
    case PendienteRevision = 'pendiente_revision';
    case Aprobada = 'aprobada';
    case Rechazada = 'rechazada';
    case Retirada = 'retirada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::PendientePago => 'Pendiente de pago',
            self::PendienteRevision => 'En revisión',
            self::Aprobada => 'Aprobada',
            self::Rechazada => 'Rechazada',
            self::Retirada => 'Retirada',
        };
    }

    public function estaPagada(): bool
    {
        return in_array($this, [self::PendienteRevision, self::Aprobada], true);
    }

    /**
     * Una inscripción cerrada ya no acepta pagos ni revisión.
     */
    public function estaCerrada(): bool
    {
        return in_array($this, [self::Rechazada, self::Retirada], true);
    }
}
