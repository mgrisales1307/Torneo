<?php

namespace Tests\Unit;

use App\Servicios\Torneos\Cuadros\EmparejadorSuizo;
use PHPUnit\Framework\TestCase;

class EmparejadorSuizoTest extends TestCase
{
    public function test_la_primera_ronda_cruza_la_mitad_alta_con_la_baja(): void
    {
        $ronda = (new EmparejadorSuizo)->emparejar([1, 2, 3, 4, 5, 6, 7, 8]);

        $this->assertSame([
            ['a' => 1, 'b' => 5],
            ['a' => 2, 'b' => 6],
            ['a' => 3, 'b' => 7],
            ['a' => 4, 'b' => 8],
        ], $ronda['cruces']);
        $this->assertNull($ronda['descansa']);
    }

    public function test_con_numero_impar_descansa_el_ultimo_que_no_haya_descansado(): void
    {
        $emparejador = new EmparejadorSuizo;

        $this->assertSame(5, $emparejador->emparejar([1, 2, 3, 4, 5])['descansa']);
        $this->assertSame(4, $emparejador->emparejar([1, 2, 3, 4, 5], [1 => [2]], [5])['descansa']);
    }

    public function test_no_repite_rivales_si_hay_otra_salida(): void
    {
        // Clasificación tras la ronda 1: 1 y 2 ganaron, 3 y 4 perdieron.
        $rivales = [1 => [3], 3 => [1], 2 => [4], 4 => [2]];

        $ronda = (new EmparejadorSuizo)->emparejar([1, 2, 3, 4], $rivales);

        $this->assertSame([['a' => 1, 'b' => 2], ['a' => 3, 'b' => 4]], $ronda['cruces']);
    }

    public function test_retrocede_cuando_el_emparejamiento_obvio_deja_una_revancha(): void
    {
        // Si 1 juega con 2, a 3 y 4 solo les queda la revancha: hay que buscar
        // otra combinación.
        $rivales = [3 => [4], 4 => [3]];

        $cruces = (new EmparejadorSuizo)->emparejar([1, 2, 3, 4], $rivales)['cruces'];

        foreach ($cruces as $cruce) {
            $this->assertNotSame([3, 4], [min($cruce), max($cruce)]);
        }
    }

    public function test_las_rondas_recomendadas_bastan_para_un_solo_invicto(): void
    {
        $emparejador = new EmparejadorSuizo;

        $this->assertSame(2, $emparejador->rondasRecomendadas(4));
        $this->assertSame(3, $emparejador->rondasRecomendadas(8));
        $this->assertSame(4, $emparejador->rondasRecomendadas(10));
    }
}
