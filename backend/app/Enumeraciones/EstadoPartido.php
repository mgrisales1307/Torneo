<?php

namespace App\Enumeraciones;

enum EstadoPartido: string
{
    case Programado = 'programado';
    case EnJuego = 'en_juego';
    case Finalizado = 'finalizado';
    case Aplazado = 'aplazado';
    case Cancelado = 'cancelado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Programado => 'Programado',
            self::EnJuego => 'En juego',
            self::Finalizado => 'Finalizado',
            self::Aplazado => 'Aplazado',
            self::Cancelado => 'Cancelado',
        };
    }

    /**
     * Solo los partidos finalizados suman en la tabla de posiciones.
     */
    public function cuentaParaLaTabla(): bool
    {
        return $this === self::Finalizado;
    }
}
