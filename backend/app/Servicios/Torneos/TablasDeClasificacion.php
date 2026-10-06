<?php

namespace App\Servicios\Torneos;

use App\Enumeraciones\TipoDeFase;
use App\Models\Equipo;
use App\Models\Fase;
use App\Models\Inscripcion;
use App\Models\Partido;
use App\Models\ResultadoBattleRoyale;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Las tablas de las fases que se ordenan por puntos: grupos, suizo y battle
 * royale.
 *
 * Ninguna tabla se guarda en una columna: se derivan siempre de los partidos
 * finalizados, así que es imposible que contradigan a los resultados. Para no
 * recalcular en cada visita se guardan en caché, y RegistroDeResultados las
 * invalida con `olvidar()` cada vez que cambia un marcador.
 *
 * Criterios de desempate:
 *   Grupos        puntos (3 por victoria), diferencia de mapas, mapas ganados, semilla
 *   Suizo         victorias, Buchholz (victorias de los rivales), diferencia de mapas, semilla
 *   Battle royale puntos, partidas ganadas, bajas, semilla
 *
 * La semilla va al final para que el orden sea estable y no baile entre
 * recargas cuando dos equipos empatan en todo.
 */
class TablasDeClasificacion
{
    private const SEGUNDOS_EN_CACHE = 300;

    private const PUNTOS_POR_VICTORIA = 3;

    /**
     * Grupos: letra => filas. Suizo y battle royale: ['general' => filas].
     * Eliminación: vacío, porque se lee en el cuadro.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function deFase(Fase $fase): array
    {
        if (! $fase->tipo->tieneTabla()) {
            return [];
        }

        return Cache::remember(
            $this->claveDeCache($fase),
            self::SEGUNDOS_EN_CACHE,
            fn (): array => $this->calcular($fase),
        );
    }

    /**
     * La tabla recién calculada, sin pasar por la caché. La usa el avance del
     * torneo, que no puede emparejar con datos de hace cinco minutos.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function calcular(Fase $fase): array
    {
        return match ($fase->tipo) {
            TipoDeFase::Grupos => $this->deGrupos($fase),
            TipoDeFase::Suizo => ['general' => $this->delSuizo($fase)],
            TipoDeFase::BattleRoyale => ['general' => $this->deBattleRoyale($fase)],
            default => [],
        };
    }

    public function olvidar(Fase $fase): void
    {
        Cache::forget($this->claveDeCache($fase));
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function deGrupos(Fase $fase): array
    {
        $inscripciones = $this->participantes($fase);
        $partidos = $this->duelosFinalizados($fase);
        $tablas = [];

        foreach ($inscripciones->groupBy('grupo')->sortKeys() as $letra => $delGrupo) {
            $filas = $this->filasVacias($delGrupo, ['jugados', 'ganados', 'perdidos', 'mapas_a_favor', 'mapas_en_contra', 'diferencia', 'puntos']);

            foreach ($partidos->where('grupo', $letra) as $partido) {
                $this->sumarDuelo($filas, $partido);
            }

            foreach ($filas as &$fila) {
                $fila['puntos'] = $fila['ganados'] * self::PUNTOS_POR_VICTORIA;
            }
            unset($fila);

            $tablas[(string) $letra] = $this->ordenar($filas, [
                ['puntos', 'desc'],
                ['diferencia', 'desc'],
                ['mapas_a_favor', 'desc'],
                ['semilla', 'asc'],
            ]);
        }

        return $tablas;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function delSuizo(Fase $fase): array
    {
        $filas = $this->filasVacias($this->participantes($fase), ['jugados', 'ganados', 'perdidos', 'mapas_a_favor', 'mapas_en_contra', 'diferencia', 'buchholz', 'descansos']);
        $rivales = [];

        foreach (Partido::query()->where('fase_id', $fase->getKey())->finalizados()->get() as $partido) {
            if ($partido->es_descanso) {
                if (isset($filas[$partido->ganador_id])) {
                    $filas[$partido->ganador_id]['ganados']++;
                    $filas[$partido->ganador_id]['descansos']++;
                }

                continue;
            }

            $this->sumarDuelo($filas, $partido);
            $rivales[$partido->equipo_a_id][] = $partido->equipo_b_id;
            $rivales[$partido->equipo_b_id][] = $partido->equipo_a_id;
        }

        // Buchholz: la suma de las victorias de los rivales enfrentados. Premia
        // a quien llegó a su récord contra rivales más difíciles.
        foreach ($filas as $equipoId => &$fila) {
            $fila['buchholz'] = array_sum(array_map(
                fn (int $rival): int => $filas[$rival]['ganados'] ?? 0,
                $rivales[$equipoId] ?? [],
            ));
        }
        unset($fila);

        return $this->ordenar($filas, [
            ['ganados', 'desc'],
            ['buchholz', 'desc'],
            ['diferencia', 'desc'],
            ['semilla', 'asc'],
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function deBattleRoyale(Fase $fase): array
    {
        $filas = $this->filasVacias($this->participantes($fase), ['partidas', 'victorias', 'bajas', 'puntos']);

        $resultados = ResultadoBattleRoyale::query()
            ->whereIn('partido_id', Partido::query()->where('fase_id', $fase->getKey())->finalizados()->select('id'))
            ->get();

        foreach ($resultados as $resultado) {
            if (! isset($filas[$resultado->equipo_id])) {
                continue;
            }

            $fila = &$filas[$resultado->equipo_id];
            $fila['partidas']++;
            $fila['bajas'] += $resultado->bajas;
            $fila['puntos'] += $resultado->puntos;
            $fila['victorias'] += $resultado->posicion === 1 ? 1 : 0;
            unset($fila);
        }

        return $this->ordenar($filas, [
            ['puntos', 'desc'],
            ['victorias', 'desc'],
            ['bajas', 'desc'],
            ['semilla', 'asc'],
        ]);
    }

    /**
     * @return Collection<int, Inscripcion>
     */
    private function participantes(Fase $fase): Collection
    {
        return Inscripcion::query()
            ->where('torneo_id', $fase->torneo_id)
            ->aprobadas()
            ->whereNotNull('semilla')
            ->with('equipo')
            ->orderBy('semilla')
            ->get();
    }

    /**
     * @return Collection<int, Partido>
     */
    private function duelosFinalizados(Fase $fase): Collection
    {
        return Partido::query()
            ->where('fase_id', $fase->getKey())
            ->finalizados()
            ->duelos()
            ->get();
    }

    /**
     * @param  Collection<int, Inscripcion>  $inscripciones
     * @param  list<string>  $columnas
     * @return array<int, array<string, mixed>>
     */
    private function filasVacias(Collection $inscripciones, array $columnas): array
    {
        $filas = [];

        foreach ($inscripciones as $inscripcion) {
            /** @var Equipo $equipo */
            $equipo = $inscripcion->equipo;

            $filas[$equipo->getKey()] = [
                'posicion' => 0,
                'semilla' => $inscripcion->semilla,
                'equipo' => $equipo->resumen(),
                ...array_fill_keys($columnas, 0),
            ];
        }

        return $filas;
    }

    /**
     * @param  array<int, array<string, mixed>>  $filas
     */
    private function sumarDuelo(array &$filas, Partido $partido): void
    {
        $a = $partido->equipo_a_id;
        $b = $partido->equipo_b_id;

        // Un partido de un equipo que ya no está en la tabla no se cuenta.
        if (! isset($filas[$a], $filas[$b])) {
            return;
        }

        $mapasA = (int) $partido->victorias_a;
        $mapasB = (int) $partido->victorias_b;

        foreach ([[$a, $mapasA, $mapasB], [$b, $mapasB, $mapasA]] as [$equipo, $favor, $contra]) {
            $filas[$equipo]['jugados']++;
            $filas[$equipo]['mapas_a_favor'] += $favor;
            $filas[$equipo]['mapas_en_contra'] += $contra;
            $filas[$equipo]['diferencia'] = $filas[$equipo]['mapas_a_favor'] - $filas[$equipo]['mapas_en_contra'];
            $filas[$equipo][$partido->ganador_id === $equipo ? 'ganados' : 'perdidos']++;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $filas
     * @param  list<array{0: string, 1: string}>  $criterios
     * @return list<array<string, mixed>>
     */
    private function ordenar(array $filas, array $criterios): array
    {
        return collect($filas)
            ->sortBy($criterios)
            ->values()
            ->map(function (array $fila, int $indice): array {
                $fila['posicion'] = $indice + 1;

                return $fila;
            })
            ->all();
    }

    private function claveDeCache(Fase $fase): string
    {
        return "fase:{$fase->getKey()}:tabla";
    }
}
