<?php

namespace App\Enumeraciones;

/**
 * Recorrido de un partido:
 *
 *   pendiente ──llegan los dos rivales──► por_jugar ──un capitán reporta──► reportado
 *                                             │                                │
 *                                             │              el rival confirma │ el rival reporta otro
 *                                             ▼                                ▼ marcador
 *                                        finalizado ◄──la organización── en_disputa
 */
enum EstadoPartido: string
{
    case Pendiente = 'pendiente';
    case PorJugar = 'por_jugar';
    case Reportado = 'reportado';
    case EnDisputa = 'en_disputa';
    case Finalizado = 'finalizado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Esperando rival',
            self::PorJugar => 'Por jugar',
            self::Reportado => 'Esperando confirmación',
            self::EnDisputa => 'En disputa',
            self::Finalizado => 'Finalizado',
        };
    }

    /**
     * Estados en los que los capitanes pueden reportar el marcador.
     */
    public function admiteReporte(): bool
    {
        return in_array($this, [self::PorJugar, self::Reportado, self::EnDisputa], true);
    }

    /**
     * Ya se jugó o se está reportando: el partido no puede cambiar de rivales.
     */
    public function yaSeJugo(): bool
    {
        return in_array($this, [self::Reportado, self::EnDisputa, self::Finalizado], true);
    }
}
