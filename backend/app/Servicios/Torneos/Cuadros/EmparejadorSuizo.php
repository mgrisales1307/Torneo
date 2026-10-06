<?php

namespace App\Servicios\Torneos\Cuadros;

/**
 * Empareja una ronda del sistema suizo. No toca la base de datos.
 *
 * En la primera ronda la mitad alta de las semillas juega contra la baja (la 1
 * contra la primera de abajo). Después, cada equipo juega contra otro con su
 * mismo récord, nunca contra uno que ya enfrentó. Si el número es impar,
 * descansa el peor clasificado que todavía no descansó, y el descanso cuenta
 * como victoria.
 */
class EmparejadorSuizo
{
    /**
     * Tope de intentos de la búsqueda con retroceso. Con los cupos de la
     * plataforma no se acerca nunca, pero si llegara se acepta una revancha
     * antes que colgar la petición.
     */
    private const MAXIMO_DE_INTENTOS = 20_000;

    private int $intentos = 0;

    /**
     * @param  list<int>  $clasificacion  Equipos ordenados del primero al último.
     * @param  array<int, list<int>>  $rivalesPrevios  Equipo => rivales que ya enfrentó.
     * @param  list<int>  $yaDescansaron
     * @return array{cruces: list<array{a: int, b: int}>, descansa: int|null}
     */
    public function emparejar(array $clasificacion, array $rivalesPrevios = [], array $yaDescansaron = []): array
    {
        $equipos = array_values($clasificacion);
        $descansa = null;

        if (count($equipos) % 2 === 1) {
            $descansa = $this->quienDescansa($equipos, $yaDescansaron);
            $equipos = array_values(array_filter($equipos, fn (int $equipo): bool => $equipo !== $descansa));
        }

        if ($rivalesPrevios === []) {
            return ['cruces' => $this->mitadContraMitad($equipos), 'descansa' => $descansa];
        }

        $this->intentos = 0;
        $cruces = $this->buscar($equipos, $rivalesPrevios);

        // Si no hay forma de evitar todas las revanchas, se empareja en orden.
        $cruces ??= array_map(
            fn (array $par): array => ['a' => $par[0], 'b' => $par[1]],
            array_chunk($equipos, 2),
        );

        return ['cruces' => $cruces, 'descansa' => $descansa];
    }

    /**
     * Rondas necesarias para que quede un solo invicto.
     */
    public function rondasRecomendadas(int $equipos): int
    {
        return max(1, (int) ceil(log(max(2, $equipos), 2)));
    }

    /**
     * @param  list<int>  $equipos
     * @param  list<int>  $yaDescansaron
     */
    private function quienDescansa(array $equipos, array $yaDescansaron): int
    {
        foreach (array_reverse($equipos) as $equipo) {
            if (! in_array($equipo, $yaDescansaron, true)) {
                return $equipo;
            }
        }

        return $equipos[count($equipos) - 1];
    }

    /**
     * @param  list<int>  $equipos
     * @return list<array{a: int, b: int}>
     */
    private function mitadContraMitad(array $equipos): array
    {
        $mitad = intdiv(count($equipos), 2);
        $cruces = [];

        for ($indice = 0; $indice < $mitad; $indice++) {
            $cruces[] = ['a' => $equipos[$indice], 'b' => $equipos[$indice + $mitad]];
        }

        return $cruces;
    }

    /**
     * Toma al mejor clasificado sin rival y prueba con los siguientes en orden:
     * como la lista está ordenada por récord, el primero válido es el más
     * parecido. Si más adelante no hay salida, retrocede y prueba con otro.
     *
     * @param  list<int>  $pendientes
     * @param  array<int, list<int>>  $rivalesPrevios
     * @return list<array{a: int, b: int}>|null
     */
    private function buscar(array $pendientes, array $rivalesPrevios): ?array
    {
        if ($pendientes === []) {
            return [];
        }

        if (++$this->intentos > self::MAXIMO_DE_INTENTOS) {
            return null;
        }

        $primero = array_shift($pendientes);

        foreach ($pendientes as $indice => $candidato) {
            if (in_array($candidato, $rivalesPrevios[$primero] ?? [], true)) {
                continue;
            }

            $resto = $pendientes;
            unset($resto[$indice]);

            $demas = $this->buscar(array_values($resto), $rivalesPrevios);

            if ($demas !== null) {
                return [['a' => $primero, 'b' => $candidato], ...$demas];
            }
        }

        return null;
    }
}
