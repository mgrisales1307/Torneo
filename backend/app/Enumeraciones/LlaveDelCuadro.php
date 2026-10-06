<?php

namespace App\Enumeraciones;

/**
 * Lado del cuadro de eliminación. En la eliminación simple todo es llave
 * superior; la doble suma la inferior (los que ya perdieron una vez) y la gran
 * final.
 */
enum LlaveDelCuadro: string
{
    case Superior = 'superior';
    case Inferior = 'inferior';
    case GranFinal = 'gran_final';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Superior => 'Llave superior',
            self::Inferior => 'Llave inferior',
            self::GranFinal => 'Gran final',
        };
    }
}
