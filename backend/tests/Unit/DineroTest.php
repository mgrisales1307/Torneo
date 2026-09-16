<?php

namespace Tests\Unit;

use App\Soporte\Dinero;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DineroTest extends TestCase
{
    /**
     * @return array<string, array{int, string, string}>
     */
    public static function montos(): array
    {
        return [
            'gratis' => [0, 'COP', '$ 0'],
            'pesos redondos' => [15_000_000, 'COP', '$ 150.000'],
            'con centavos' => [1_234_56, 'COP', '$ 1.234,56'],
            'un centavo' => [1, 'COP', '$ 0,01'],
            'euros' => [2_500, 'EUR', '€ 25'],
            'moneda desconocida' => [10_000, 'XYZ', 'XYZ 100'],
        ];
    }

    #[DataProvider('montos')]
    public function test_formatea_el_dinero_en_pesos_y_centavos(int $centavos, string $moneda, string $esperado): void
    {
        $this->assertSame($esperado, Dinero::formatear($centavos, $moneda));
    }
}
