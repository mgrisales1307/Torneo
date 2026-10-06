<?php

namespace Tests\Unit;

use App\Enumeraciones\LlaveDelCuadro;
use App\Servicios\Torneos\Cuadros\ConstructorDeEliminacion;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ConstructorDeEliminacionTest extends TestCase
{
    private ConstructorDeEliminacion $constructor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->constructor = new ConstructorDeEliminacion;
    }

    public function test_el_orden_de_siembra_enfrenta_a_cada_semilla_con_su_espejo(): void
    {
        $this->assertSame([1, 2], $this->constructor->ordenDeSiembra(2));
        $this->assertSame([1, 4, 2, 3], $this->constructor->ordenDeSiembra(4));
        $this->assertSame([1, 8, 4, 5, 2, 7, 3, 6], $this->constructor->ordenDeSiembra(8));
    }

    public function test_con_ocho_equipos_la_simple_tiene_siete_partidos_y_una_final(): void
    {
        $partidos = $this->constructor->construir(range(101, 108), false);

        $this->assertCount(7, $partidos);
        $this->assertCount(1, array_filter($partidos, fn (array $partido): bool => $partido['es_final']));

        // Primera ronda: 1 contra 8, 4 contra 5, 2 contra 7 y 3 contra 6.
        $primera = array_values(array_filter($partidos, fn (array $partido): bool => $partido['ronda'] === 1));
        $this->assertSame([[101, 108], [104, 105], [102, 107], [103, 106]], array_map(
            fn (array $partido): array => [$partido['equipo_a'], $partido['equipo_b']],
            $primera,
        ));
    }

    public function test_los_byes_le_tocan_a_las_mejores_semillas_y_no_generan_partidos(): void
    {
        // Seis equipos en un cuadro de ocho: las semillas 1 y 2 pasan directo.
        $partidos = $this->constructor->construir(range(1, 6), false);

        $this->assertCount(5, $partidos);

        $primera = array_values(array_filter($partidos, fn (array $partido): bool => $partido['ronda'] === 1));
        $this->assertCount(2, $primera);

        $segunda = array_values(array_filter($partidos, fn (array $partido): bool => $partido['ronda'] === 2));
        $this->assertSame(1, $segunda[0]['equipo_a']);
        $this->assertSame(2, $segunda[1]['equipo_a']);
        $this->assertNull($segunda[0]['equipo_b']);
    }

    /**
     * @return array<string, array{int, int}>
     */
    public static function cantidadesDeLaDoble(): array
    {
        // 2n − 1 partidos: cada equipo menos el campeón pierde dos veces, y el
        // campeón pierde como mucho una (en la gran final no hay revancha).
        return [
            'cuatro equipos' => [4, 6],
            'ocho equipos' => [8, 14],
            'dieciséis equipos' => [16, 30],
        ];
    }

    #[DataProvider('cantidadesDeLaDoble')]
    public function test_la_doble_tiene_dos_n_menos_uno_partidos(int $equipos, int $esperados): void
    {
        $partidos = $this->constructor->construir(range(1, $equipos), true);

        $this->assertCount($esperados, $partidos);
        $this->assertSame(LlaveDelCuadro::GranFinal, end($partidos)['llave']);
        $this->assertTrue(end($partidos)['es_final']);
    }

    public function test_en_la_doble_el_perdedor_de_arriba_baja_a_la_llave_inferior(): void
    {
        $partidos = $this->conClave($this->constructor->construir(range(1, 8), true));

        foreach ($partidos as $partido) {
            if ($partido['llave'] === LlaveDelCuadro::Superior) {
                $this->assertNotNull($partido['siguiente_perdedor'], "El perdedor de {$partido['clave']} no tiene a dónde ir.");
                $this->assertSame(LlaveDelCuadro::Inferior, $partidos[$partido['siguiente_perdedor']['clave']]['llave']);
            }

            if ($partido['llave'] === LlaveDelCuadro::Inferior) {
                $this->assertNull($partido['siguiente_perdedor'], 'Quien pierde abajo queda eliminado.');
            }
        }
    }

    public function test_cada_partido_recibe_exactamente_dos_rivales(): void
    {
        foreach ([[5, false], [6, true], [8, true], [13, true]] as [$equipos, $doble]) {
            $partidos = $this->constructor->construir(range(1, $equipos), $doble);
            $llegadas = [];

            foreach ($partidos as $partido) {
                foreach (['a', 'b'] as $ranura) {
                    if ($partido["equipo_$ranura"] !== null) {
                        $llegadas[$partido['clave']][] = $ranura;
                    }
                }

                foreach (['siguiente_ganador', 'siguiente_perdedor'] as $destino) {
                    if ($partido[$destino] !== null) {
                        $llegadas[$partido[$destino]['clave']][] = $partido[$destino]['ranura'];
                    }
                }
            }

            foreach ($partidos as $partido) {
                $ranuras = $llegadas[$partido['clave']] ?? [];
                sort($ranuras);

                $this->assertSame(['a', 'b'], $ranuras, "Con $equipos equipos, {$partido['clave']} no recibe a dos rivales.");
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $partidos
     * @return array<string, array<string, mixed>>
     */
    private function conClave(array $partidos): array
    {
        return array_column($partidos, null, 'clave');
    }
}
