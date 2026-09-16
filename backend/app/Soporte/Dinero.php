<?php

namespace App\Soporte;

/**
 * El dinero se guarda y se opera siempre en centavos, como entero. Esta clase
 * es el único lugar donde se convierte a texto para mostrarlo.
 *
 * Nunca se usan flotantes: 0.1 + 0.2 no es 0.3 en coma flotante, y una
 * inscripción que no cuadra por un centavo es un problema real.
 */
final class Dinero
{
    public static function formatear(int $centavos, string $moneda = 'COP'): string
    {
        $unidades = intdiv($centavos, 100);
        $resto = $centavos % 100;

        $texto = number_format($unidades, 0, ',', '.');

        if ($resto !== 0) {
            $texto .= ','.str_pad((string) $resto, 2, '0', STR_PAD_LEFT);
        }

        return self::simbolo($moneda).' '.$texto;
    }

    private static function simbolo(string $moneda): string
    {
        return match (strtoupper($moneda)) {
            'COP', 'USD', 'MXN', 'ARS', 'CLP' => '$',
            'EUR' => '€',
            default => strtoupper($moneda),
        };
    }
}
