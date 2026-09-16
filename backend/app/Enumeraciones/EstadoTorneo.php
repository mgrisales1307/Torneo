<?php

namespace App\Enumeraciones;

/**
 * Ciclo de vida de un torneo. El orden de los casos es el orden real del
 * proceso: un torneo nunca retrocede salvo que se cancele.
 */
enum EstadoTorneo: string
{
    case Borrador = 'borrador';
    case InscripcionesAbiertas = 'inscripciones_abiertas';
    case InscripcionesCerradas = 'inscripciones_cerradas';
    case EnCurso = 'en_curso';
    case Finalizado = 'finalizado';
    case Cancelado = 'cancelado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::InscripcionesAbiertas => 'Inscripciones abiertas',
            self::InscripcionesCerradas => 'Inscripciones cerradas',
            self::EnCurso => 'En curso',
            self::Finalizado => 'Finalizado',
            self::Cancelado => 'Cancelado',
        };
    }

    /**
     * Un torneo en borrador solo lo ve la organización.
     */
    public function esVisibleAlPublico(): bool
    {
        return $this !== self::Borrador;
    }

    public function admiteInscripciones(): bool
    {
        return $this === self::InscripcionesAbiertas;
    }

    /**
     * Estados en los que ya no tiene sentido tocar el calendario.
     */
    public function estaCerrado(): bool
    {
        return in_array($this, [self::Finalizado, self::Cancelado], true);
    }
}
