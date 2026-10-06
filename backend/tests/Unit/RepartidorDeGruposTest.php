<?php

namespace Tests\Unit;

use App\Servicios\Torneos\Cuadros\RepartidorDeGrupos;
use PHPUnit\Framework\TestCase;

class RepartidorDeGruposTest extends TestCase
{
    public function test_reparte_en_serpiente(): void
    {
        $grupos = (new RepartidorDeGrupos)->repartir(range(1, 8), 2);

        $this->assertSame(['A' => [1, 4, 5, 8], 'B' => [2, 3, 6, 7]], $grupos);
    }

    public function test_el_todos_contra_todos_cruza_a_cada_par_una_sola_vez(): void
    {
        foreach ([4, 5, 6] as $cantidad) {
            $cruces = (new RepartidorDeGrupos)->todosContraTodos(range(1, $cantidad));
            $pares = array_map(fn (array $cruce): string => min($cruce['a'], $cruce['b']).'-'.max($cruce['a'], $cruce['b']), $cruces);

            $this->assertCount($cantidad * ($cantidad - 1) / 2, $cruces);
            $this->assertCount(count($pares), array_unique($pares));
        }
    }

    public function test_nadie_juega_dos_veces_en_la_misma_ronda(): void
    {
        $cruces = (new RepartidorDeGrupos)->todosContraTodos(range(1, 6));

        foreach (collect($cruces)->groupBy('ronda') as $ronda) {
            $equipos = $ronda->flatMap(fn (array $cruce): array => [$cruce['a'], $cruce['b']]);

            $this->assertCount($equipos->count(), $equipos->unique());
        }
    }
}
