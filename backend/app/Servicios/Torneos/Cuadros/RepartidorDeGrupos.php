<?php

namespace App\Servicios\Torneos\Cuadros;

/**
 * Reparte los equipos en grupos y arma el todos contra todos de cada uno. No
 * toca la base de datos.
 */
class RepartidorDeGrupos
{
    /**
     * Reparto en serpiente: la semilla 1 va al grupo A, la 2 al B… y al llegar
     * al último grupo se vuelve en sentido contrario. Así cada grupo recibe una
     * mezcla pareja de semillas altas y bajas.
     *
     * @param  list<int>  $semillas  Identificadores de equipo, el primero es la semilla 1.
     * @return array<string, list<int>> Letra del grupo => equipos.
     */
    public function repartir(array $semillas, int $cantidadDeGrupos): array
    {
        $letras = array_slice(range('A', 'Z'), 0, $cantidadDeGrupos);
        $grupos = array_fill_keys($letras, []);

        foreach ($semillas as $indice => $equipo) {
            $fila = intdiv($indice, $cantidadDeGrupos);
            $columna = $indice % $cantidadDeGrupos;

            if ($fila % 2 === 1) {
                $columna = $cantidadDeGrupos - 1 - $columna;
            }

            $grupos[$letras[$columna]][] = $equipo;
        }

        return $grupos;
    }

    /**
     * Todos contra todos con el método del círculo (Berger): los equipos se
     * ponen en dos filas enfrentadas, uno queda fijo y el resto rota una
     * posición por ronda. Con n equipos salen n − 1 rondas sin repetir cruces.
     * Si son impares se agrega un hueco, y a quien le toca, descansa.
     *
     * @param  list<int>  $equipos
     * @return list<array{ronda: int, a: int, b: int}>
     */
    public function todosContraTodos(array $equipos): array
    {
        $rueda = $equipos;

        if (count($rueda) % 2 !== 0) {
            $rueda[] = null;
        }

        $cantidad = count($rueda);
        $cruces = [];

        for ($ronda = 1; $ronda < $cantidad; $ronda++) {
            for ($posicion = 0; $posicion < $cantidad / 2; $posicion++) {
                $uno = $rueda[$posicion];
                $otro = $rueda[$cantidad - 1 - $posicion];

                if ($uno !== null && $otro !== null) {
                    $cruces[] = ['ronda' => $ronda, 'a' => $uno, 'b' => $otro];
                }
            }

            $fijo = array_shift($rueda);
            $ultimo = array_pop($rueda);
            $rueda = [$fijo, $ultimo, ...$rueda];
        }

        return $cruces;
    }
}
