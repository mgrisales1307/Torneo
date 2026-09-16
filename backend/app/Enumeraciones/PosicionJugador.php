<?php

namespace App\Enumeraciones;

enum PosicionJugador: string
{
    case Portero = 'portero';
    case Defensa = 'defensa';
    case Mediocampista = 'mediocampista';
    case Delantero = 'delantero';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Portero => 'Portero',
            self::Defensa => 'Defensa',
            self::Mediocampista => 'Mediocampista',
            self::Delantero => 'Delantero',
        };
    }

    public function abreviatura(): string
    {
        return match ($this) {
            self::Portero => 'POR',
            self::Defensa => 'DEF',
            self::Mediocampista => 'MED',
            self::Delantero => 'DEL',
        };
    }
}
