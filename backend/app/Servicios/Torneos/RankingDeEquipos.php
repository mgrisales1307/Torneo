<?php

namespace App\Servicios\Torneos;

use App\Enumeraciones\Juego;
use App\Models\Equipo;
use App\Models\Partido;
use App\Models\ResultadoBattleRoyale;
use Illuminate\Support\Facades\Cache;

/**
 * Ranking de los equipos de cada juego, con todos los torneos jugados.
 *
 * - En los juegos de duelo es un Elo: todos arrancan en 1000 y cada serie
 *   pasa puntos del perdedor al ganador, más cuanto más inesperado el
 *   resultado. Ganarle al primero vale mucho; ganarle al último, poco.
 * - En battle royale es la suma de los puntos de todas las partidas.
 *
 * Como las tablas, se deriva de los resultados y no se guarda: se calcula, se
 * deja en caché y RegistroDeResultados la invalida. De aquí sale también la
 * siembra de los torneos nuevos.
 */
class RankingDeEquipos
{
    public const ELO_INICIAL = 1000;

    private const FACTOR_K = 32;

    private const SEGUNDOS_EN_CACHE = 300;

    /**
     * @return list<array{posicion: int, equipo: array<string, mixed>, puntos: int, partidos: int, victorias: int, derrotas: int, bajas: int}>
     */
    public function paraJuego(Juego $juego, int $limite = 50): array
    {
        return array_slice($this->completo($juego), 0, $limite);
    }

    /**
     * Puntaje de cada equipo con historial. El que no aparece no ha jugado.
     *
     * @return array<int, int>
     */
    public function puntajes(Juego $juego): array
    {
        return array_column(
            array_map(fn (array $fila): array => [$fila['equipo']['id'], $fila['puntos']], $this->completo($juego)),
            1,
            0,
        );
    }

    public function olvidar(Juego $juego): void
    {
        Cache::forget($this->claveDeCache($juego));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function completo(Juego $juego): array
    {
        return Cache::remember(
            $this->claveDeCache($juego),
            self::SEGUNDOS_EN_CACHE,
            fn (): array => $juego->esBattleRoyale() ? $this->porPuntos($juego) : $this->porElo($juego),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function porElo(Juego $juego): array
    {
        $partidos = Partido::query()
            ->whereHas('torneo', fn ($torneo) => $torneo->where('juego', $juego->value))
            ->finalizados()
            ->duelos()
            ->whereNotNull('ganador_id')
            ->orderBy('finalizado_en')
            ->orderBy('id')
            ->get(['equipo_a_id', 'equipo_b_id', 'ganador_id']);

        $filas = [];

        foreach ($partidos as $partido) {
            foreach ([$partido->equipo_a_id, $partido->equipo_b_id] as $equipo) {
                $filas[$equipo] ??= ['elo' => (float) self::ELO_INICIAL, 'partidos' => 0, 'victorias' => 0, 'derrotas' => 0, 'bajas' => 0];
            }

            $ganador = $partido->ganador_id;
            $perdedor = $ganador === $partido->equipo_a_id ? $partido->equipo_b_id : $partido->equipo_a_id;

            $esperado = 1 / (1 + 10 ** (($filas[$perdedor]['elo'] - $filas[$ganador]['elo']) / 400));
            $cambio = self::FACTOR_K * (1 - $esperado);

            $filas[$ganador]['elo'] += $cambio;
            $filas[$perdedor]['elo'] -= $cambio;
            $filas[$ganador]['partidos']++;
            $filas[$perdedor]['partidos']++;
            $filas[$ganador]['victorias']++;
            $filas[$perdedor]['derrotas']++;
        }

        foreach ($filas as &$fila) {
            $fila['puntos'] = (int) round($fila['elo']);
            unset($fila['elo']);
        }
        unset($fila);

        return $this->conEquipos($filas);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function porPuntos(Juego $juego): array
    {
        $resultados = ResultadoBattleRoyale::query()
            ->whereHas('partido', fn ($partido) => $partido
                ->finalizados()
                ->whereHas('torneo', fn ($torneo) => $torneo->where('juego', $juego->value)))
            ->get(['equipo_id', 'posicion', 'bajas', 'puntos']);

        $filas = [];

        foreach ($resultados as $resultado) {
            $fila = &$filas[$resultado->equipo_id];
            $fila ??= ['puntos' => 0, 'partidos' => 0, 'victorias' => 0, 'derrotas' => 0, 'bajas' => 0];
            $fila['puntos'] += $resultado->puntos;
            $fila['partidos']++;
            $fila['bajas'] += $resultado->bajas;
            $fila['victorias'] += $resultado->posicion === 1 ? 1 : 0;
            unset($fila);
        }

        return $this->conEquipos($filas);
    }

    /**
     * @param  array<int, array<string, int>>  $filas
     * @return list<array<string, mixed>>
     */
    private function conEquipos(array $filas): array
    {
        $equipos = Equipo::withTrashed()->whereIn('id', array_keys($filas))->get()->keyBy('id');

        $conEquipo = [];

        foreach ($filas as $equipoId => $fila) {
            $equipo = $equipos->get($equipoId);

            if ($equipo === null || $equipo->trashed()) {
                continue;
            }

            $conEquipo[] = ['equipo' => $equipo->resumen(), ...$fila];
        }

        return collect($conEquipo)
            ->sortBy([['puntos', 'desc'], ['victorias', 'desc'], ['equipo.nombre', 'asc']])
            ->values()
            ->map(fn (array $fila, int $indice): array => ['posicion' => $indice + 1, ...$fila])
            ->all();
    }

    private function claveDeCache(Juego $juego): string
    {
        return "ranking:{$juego->value}";
    }
}
