<?php

namespace App\Enumeraciones;

enum TipoEventoPartido: string
{
    case Gol = 'gol';
    case GolDePenal = 'gol_de_penal';
    case Autogol = 'autogol';
    case TarjetaAmarilla = 'tarjeta_amarilla';
    case TarjetaRoja = 'tarjeta_roja';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Gol => 'Gol',
            self::GolDePenal => 'Gol de penal',
            self::Autogol => 'Autogol',
            self::TarjetaAmarilla => 'Tarjeta amarilla',
            self::TarjetaRoja => 'Tarjeta roja',
        };
    }

    /**
     * Suma al marcador del equipo al que pertenece el evento. El autogol no:
     * se registra al jugador que lo hizo, pero el gol es del rival.
     */
    public function sumaAlMarcador(): bool
    {
        return in_array($this, [self::Gol, self::GolDePenal], true);
    }

    /**
     * Cuenta para la tabla de goleadores.
     */
    public function cuentaComoGolDelJugador(): bool
    {
        return in_array($this, [self::Gol, self::GolDePenal], true);
    }
}
