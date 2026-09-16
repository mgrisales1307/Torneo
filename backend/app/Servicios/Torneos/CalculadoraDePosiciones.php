<?php

namespace App\Servicios\Torneos;

use App\Models\Equipo;
use App\Models\Partido;
use App\Models\Torneo;
use App\Servicios\Medios\AlmacenDeImagenes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Calcula la tabla de posiciones a partir de los partidos finalizados.
 *
 * La tabla no se guarda en ninguna columna: se deriva siempre de los
 * resultados, así que es imposible que quede desactualizada o contradiga a los
 * partidos. Para que eso no cueste una consulta pesada en cada visita, el
 * resultado se guarda en caché y los servicios que tocan resultados lo
 * invalidan con `olvidar()`.
 *
 * Criterios de ordenamiento, en este orden:
 *   1. Puntos
 *   2. Diferencia de gol
 *   3. Goles a favor
 *   4. Nombre del equipo (para que el orden sea estable y no baile entre
 *      recargas cuando dos equipos empatan en todo)
 */
class CalculadoraDePosiciones
{
    private const SEGUNDOS_EN_CACHE = 300;

    public function __construct(private readonly AlmacenDeImagenes $imagenes) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function paraTorneo(Torneo $torneo): Collection
    {
        /** @var array<int, array<string, mixed>> $tabla */
        $tabla = Cache::remember(
            $this->claveDeCache($torneo),
            self::SEGUNDOS_EN_CACHE,
            fn (): array => $this->calcular($torneo)->all(),
        );

        return collect($tabla);
    }

    public function olvidar(Torneo $torneo): void
    {
        Cache::forget($this->claveDeCache($torneo));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function calcular(Torneo $torneo): Collection
    {
        $equipos = $this->equiposParticipantes($torneo);

        if ($equipos->isEmpty()) {
            return collect();
        }

        $filas = $equipos->mapWithKeys(fn (Equipo $equipo): array => [
            $equipo->getKey() => $this->filaVacia($equipo),
        ])->all();

        $partidos = $torneo->partidos()
            ->finalizados()
            ->whereNotNull('goles_local')
            ->whereNotNull('goles_visitante')
            ->orderBy('jornada')
            ->orderBy('id')
            ->get();

        foreach ($partidos as $partido) {
            $this->acumular($filas, $partido, $torneo);
        }

        return collect($filas)
            ->sortBy([
                ['puntos', 'desc'],
                ['diferencia_de_gol', 'desc'],
                ['goles_a_favor', 'desc'],
                ['equipo.nombre', 'asc'],
            ])
            ->values()
            ->map(function (array $fila, int $indice): array {
                $fila['posicion'] = $indice + 1;

                return $fila;
            });
    }

    /**
     * @return Collection<int, Equipo>
     */
    private function equiposParticipantes(Torneo $torneo): Collection
    {
        return Equipo::query()
            ->whereIn('id', $torneo->inscripciones()->aprobadas()->select('equipo_id'))
            ->orderBy('nombre')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function filaVacia(Equipo $equipo): array
    {
        return [
            'posicion' => 0,
            'equipo' => [
                'id' => $equipo->getKey(),
                'nombre' => $equipo->nombre,
                'slug' => $equipo->slug,
                'abreviatura' => $equipo->abreviatura,
                'escudo_url' => $this->imagenes->url($equipo->escudo_ruta),
                'color_primario' => $equipo->color_primario,
            ],
            'partidos_jugados' => 0,
            'ganados' => 0,
            'empatados' => 0,
            'perdidos' => 0,
            'goles_a_favor' => 0,
            'goles_en_contra' => 0,
            'diferencia_de_gol' => 0,
            'puntos' => 0,
            'forma' => [],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $filas
     */
    private function acumular(array &$filas, Partido $partido, Torneo $torneo): void
    {
        $local = $partido->equipo_local_id;
        $visitante = $partido->equipo_visitante_id;

        // Un partido de un equipo que ya no está en la lista de aprobados (por
        // ejemplo, retirado a mitad de torneo) no se cuenta.
        if (! isset($filas[$local], $filas[$visitante])) {
            return;
        }

        $golesLocal = (int) $partido->goles_local;
        $golesVisitante = (int) $partido->goles_visitante;

        $this->sumar($filas[$local], $golesLocal, $golesVisitante, $torneo);
        $this->sumar($filas[$visitante], $golesVisitante, $golesLocal, $torneo);
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function sumar(array &$fila, int $golesPropios, int $golesRival, Torneo $torneo): void
    {
        $fila['partidos_jugados']++;
        $fila['goles_a_favor'] += $golesPropios;
        $fila['goles_en_contra'] += $golesRival;
        $fila['diferencia_de_gol'] = $fila['goles_a_favor'] - $fila['goles_en_contra'];

        if ($golesPropios > $golesRival) {
            $fila['ganados']++;
            $fila['puntos'] += $torneo->puntos_por_victoria;
            $resultado = 'V';
        } elseif ($golesPropios === $golesRival) {
            $fila['empatados']++;
            $fila['puntos'] += $torneo->puntos_por_empate;
            $resultado = 'E';
        } else {
            $fila['perdidos']++;
            $fila['puntos'] += $torneo->puntos_por_derrota;
            $resultado = 'D';
        }

        // Los últimos cinco resultados, del más reciente al más antiguo.
        $fila['forma'] = array_slice(array_merge([$resultado], $fila['forma']), 0, 5);
    }

    private function claveDeCache(Torneo $torneo): string
    {
        return "torneo:{$torneo->getKey()}:tabla-de-posiciones";
    }
}
