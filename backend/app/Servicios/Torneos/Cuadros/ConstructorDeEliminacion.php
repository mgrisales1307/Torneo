<?php

namespace App\Servicios\Torneos\Cuadros;

use App\Enumeraciones\LlaveDelCuadro;
use InvalidArgumentException;

/**
 * Arma un cuadro de eliminación simple o doble a partir de los equipos
 * ordenados por semilla. No toca la base de datos: devuelve los partidos y a
 * dónde va el ganador y el perdedor de cada uno, y EscritorDeCuadros los
 * guarda.
 *
 * Cómo se arma:
 *
 * 1. El cuadro se agranda hasta la potencia de dos siguiente. Los huecos son
 *    byes, y se reparten con el orden clásico de siembra (1 contra 16, 8
 *    contra 9…), así que siempre le tocan a las mejores semillas.
 *
 * 2. En la doble, la llave inferior recibe a los que pierden en la superior.
 *    Las rondas pares de la inferior cruzan a los que vienen de la propia
 *    llave con los que acaban de caer de la superior, en orden invertido una
 *    ronda sí y otra no: así nadie repite rival justo después de perder con
 *    él. La gran final enfrenta a los campeones de las dos llaves.
 *
 * 3. Los partidos que no se van a jugar no se guardan. Un partido con un bye
 *    pasa a su ganador directo a la ronda siguiente, y uno con un solo rival
 *    posible (porque el otro lado quedó vacío por los byes) se salta: quien
 *    vaya a llegar a él sigue de largo hasta el partido de después.
 */
class ConstructorDeEliminacion
{
    /**
     * @param  list<int>  $semillas  Identificadores de equipo, el primero es la semilla 1.
     * @return list<array{
     *     clave: string,
     *     llave: LlaveDelCuadro,
     *     ronda: int,
     *     numero: int,
     *     es_final: bool,
     *     equipo_a: int|null,
     *     equipo_b: int|null,
     *     siguiente_ganador: array{clave: string, ranura: string}|null,
     *     siguiente_perdedor: array{clave: string, ranura: string}|null,
     * }>
     */
    public function construir(array $semillas, bool $doble, bool $tercerPuesto = false): array
    {
        $cantidad = count($semillas);

        if ($cantidad < 2) {
            throw new InvalidArgumentException('Un cuadro necesita al menos dos equipos.');
        }

        if ($doble && $cantidad < 4) {
            throw new InvalidArgumentException('La eliminación doble necesita al menos cuatro equipos.');
        }

        $rondas = (int) ceil(log($cantidad, 2));
        $tamano = 2 ** $rondas;

        $nodos = $this->llaveSuperior($semillas, $rondas, $tamano);

        if ($doble) {
            $nodos = [...$nodos, ...$this->llaveInferior($rondas, $tamano)];

            $nodos[] = $this->nodo(LlaveDelCuadro::GranFinal, 1, 0, [
                ['ganador', $this->clave(LlaveDelCuadro::Superior, $rondas, 0)],
                ['ganador', $this->clave(LlaveDelCuadro::Inferior, 2 * ($rondas - 1), 0)],
            ]);
        }
        if (! $doble && $tercerPuesto && $cantidad >= 4) {
            $nodos[] = $this->nodo(LlaveDelCuadro::TercerPuesto, $rondas, 0, [
                ['perdedor', $this->clave(LlaveDelCuadro::Superior, $rondas - 1, 0)],
                ['perdedor', $this->clave(LlaveDelCuadro::Superior, $rondas - 1, 1)],
            ]);
        }

        $final = $doble
            ? $this->clave(LlaveDelCuadro::GranFinal, 1, 0)
            : $this->clave(LlaveDelCuadro::Superior, $rondas, 0);

        return $this->resolver($nodos, $final);
    }

    /**
     * Posición de cada semilla en el cuadro: [1, 8, 4, 5, 2, 7, 3, 6] para 8.
     *
     * Se construye duplicando: cada semilla s de la ronda anterior queda junto
     * a su espejo (tamaño + 1 − s), que es el rival más débil que le toca.
     *
     * @return list<int>
     */
    public function ordenDeSiembra(int $tamano): array
    {
        $orden = [1];

        for ($actual = 2; $actual <= $tamano; $actual *= 2) {
            $siguiente = [];

            foreach ($orden as $semilla) {
                $siguiente[] = $semilla;
                $siguiente[] = $actual + 1 - $semilla;
            }

            $orden = $siguiente;
        }

        return $orden;
    }

    /**
     * @param  list<int>  $semillas
     * @return list<array<string, mixed>>
     */
    private function llaveSuperior(array $semillas, int $rondas, int $tamano): array
    {
        $huecos = array_map(
            fn (int $semilla): array => isset($semillas[$semilla - 1])
                ? ['equipo', $semillas[$semilla - 1]]
                : ['vacia', null],
            $this->ordenDeSiembra($tamano),
        );

        $nodos = [];

        for ($numero = 0; $numero < $tamano / 2; $numero++) {
            $nodos[] = $this->nodo(LlaveDelCuadro::Superior, 1, $numero, [
                $huecos[2 * $numero],
                $huecos[2 * $numero + 1],
            ]);
        }

        for ($ronda = 2; $ronda <= $rondas; $ronda++) {
            for ($numero = 0; $numero < $tamano / (2 ** $ronda); $numero++) {
                $nodos[] = $this->nodo(LlaveDelCuadro::Superior, $ronda, $numero, [
                    ['ganador', $this->clave(LlaveDelCuadro::Superior, $ronda - 1, 2 * $numero)],
                    ['ganador', $this->clave(LlaveDelCuadro::Superior, $ronda - 1, 2 * $numero + 1)],
                ]);
            }
        }

        return $nodos;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function llaveInferior(int $rondas, int $tamano): array
    {
        $nodos = [];

        // Ronda 1: los que pierden en la primera ronda de arriba, de a dos.
        for ($numero = 0; $numero < $tamano / 4; $numero++) {
            $nodos[] = $this->nodo(LlaveDelCuadro::Inferior, 1, $numero, [
                ['perdedor', $this->clave(LlaveDelCuadro::Superior, 1, 2 * $numero)],
                ['perdedor', $this->clave(LlaveDelCuadro::Superior, 1, 2 * $numero + 1)],
            ]);
        }

        for ($caida = 1; $caida <= $rondas - 1; $caida++) {
            $rondaPar = 2 * $caida;
            $partidos = intdiv($tamano, 2 ** ($caida + 1));

            // Ronda par: los que siguen vivos abajo contra los que acaban de
            // caer de la ronda (caída + 1) de arriba.
            for ($numero = 0; $numero < $partidos; $numero++) {
                $deArriba = $caida % 2 === 1 ? $partidos - 1 - $numero : $numero;

                $nodos[] = $this->nodo(LlaveDelCuadro::Inferior, $rondaPar, $numero, [
                    ['ganador', $this->clave(LlaveDelCuadro::Inferior, $rondaPar - 1, $numero)],
                    ['perdedor', $this->clave(LlaveDelCuadro::Superior, $caida + 1, $deArriba)],
                ]);
            }

            // Ronda impar: los ganadores de la ronda par, entre ellos.
            if ($caida < $rondas - 1) {
                for ($numero = 0; $numero < $partidos / 2; $numero++) {
                    $nodos[] = $this->nodo(LlaveDelCuadro::Inferior, $rondaPar + 1, $numero, [
                        ['ganador', $this->clave(LlaveDelCuadro::Inferior, $rondaPar, 2 * $numero)],
                        ['ganador', $this->clave(LlaveDelCuadro::Inferior, $rondaPar, 2 * $numero + 1)],
                    ]);
                }
            }
        }

        return $nodos;
    }

    /**
     * Recorre los nodos en orden (cada uno depende solo de los anteriores) y
     * decide cuáles son partidos de verdad.
     *
     * Cada salida de un nodo —su ganador y su perdedor— vale una de tres cosas:
     *   ['equipo', id]            se sabe desde ya quién es (por un bye)
     *   ['vacia', null]           nunca llegará nadie
     *   ['dinamica', clave, lado] lo decidirá el partido real `clave`
     *
     * @param  list<array<string, mixed>>  $nodos
     * @return list<array<string, mixed>>
     */
    private function resolver(array $nodos, string $final): array
    {
        $salidas = [];
        $reales = [];

        foreach ($nodos as $nodo) {
            $entradas = array_map(
                fn (array $entrada): array => match ($entrada[0]) {
                    'ganador', 'perdedor' => $salidas[$entrada[1]][$entrada[0]],
                    default => $entrada,
                },
                $nodo['entradas'],
            );

            $vacias = count(array_filter($entradas, fn (array $valor): bool => $valor[0] === 'vacia'));

            if ($vacias === 2) {
                $salidas[$nodo['clave']] = ['ganador' => ['vacia', null], 'perdedor' => ['vacia', null]];

                continue;
            }

            if ($vacias === 1) {
                // Bye o paso directo: el único que llega sigue de largo y nadie
                // pierde.
                $unico = $entradas[0][0] === 'vacia' ? $entradas[1] : $entradas[0];
                $salidas[$nodo['clave']] = ['ganador' => $unico, 'perdedor' => ['vacia', null]];

                continue;
            }

            $salidas[$nodo['clave']] = [
                'ganador' => ['dinamica', $nodo['clave'], 'ganador'],
                'perdedor' => ['dinamica', $nodo['clave'], 'perdedor'],
            ];

            $real = [
                'clave' => $nodo['clave'],
                'llave' => $nodo['llave'],
                'ronda' => $nodo['ronda'],
                'numero' => $nodo['numero'] + 1,
                'es_final' => $nodo['clave'] === $final,
                'equipo_a' => null,
                'equipo_b' => null,
                'siguiente_ganador' => null,
                'siguiente_perdedor' => null,
            ];

            foreach (['a' => $entradas[0], 'b' => $entradas[1]] as $ranura => $valor) {
                if ($valor[0] === 'equipo') {
                    $real["equipo_$ranura"] = $valor[1];

                    continue;
                }

                // Dinámica: el partido de origen apunta a este.
                [, $origen, $lado] = $valor;
                $reales[$origen]["siguiente_$lado"] = ['clave' => $nodo['clave'], 'ranura' => $ranura];
            }

            $reales[$nodo['clave']] = $real;
        }

        return array_values($reales);
    }

    /**
     * @param  array{0: array{0: string, 1: int|string|null}, 1: array{0: string, 1: int|string|null}}  $entradas
     * @return array<string, mixed>
     */
    private function nodo(LlaveDelCuadro $llave, int $ronda, int $numero, array $entradas): array
    {
        return [
            'clave' => $this->clave($llave, $ronda, $numero),
            'llave' => $llave,
            'ronda' => $ronda,
            'numero' => $numero,
            'entradas' => $entradas,
        ];
    }

    private function clave(LlaveDelCuadro $llave, int $ronda, int $numero): string
    {
        return "{$llave->value}-$ronda-$numero";
    }
}
