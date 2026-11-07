<?php

namespace Tests\Unit;

use App\Soporte\Dinero;
use PHPUnit\Framework\TestCase;

/**
 * Casos de borde del formato de dinero que DineroTest no recorre.
 */
class DineroFormatoTest extends TestCase
{
    public function test_la_moneda_se_reconoce_sin_importar_las_mayusculas(): void
    {
        $this->assertSame(Dinero::formatear(10_000, 'COP'), Dinero::formatear(10_000, 'cop'));
        $this->assertSame('€ 25', Dinero::formatear(2_500, 'eur'));
    }

    public function test_las_monedas_con_signo_de_peso_comparten_simbolo(): void
    {
        foreach (['COP', 'USD', 'MXN', 'ARS', 'CLP'] as $moneda) {
            $this->assertStringStartsWith('$ ', Dinero::formatear(100, $moneda), $moneda);
        }
    }

    public function test_los_millones_llevan_separador_de_miles(): void
    {
        $this->assertSame('$ 1.000.000', Dinero::formatear(100_000_000));
        $this->assertSame('$ 12.345.678,90', Dinero::formatear(1_234_567_890));
    }

    public function test_los_centavos_de_un_digito_se_rellenan_con_cero(): void
    {
        $this->assertSame('$ 3,05', Dinero::formatear(305));
        $this->assertSame('$ 0,10', Dinero::formatear(10));
    }

    public function test_sin_moneda_se_asume_pesos_colombianos(): void
    {
        $this->assertSame('$ 50.000', Dinero::formatear(5_000_000));
    }
}
