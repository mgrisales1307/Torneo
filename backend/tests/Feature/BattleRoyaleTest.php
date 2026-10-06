<?php

namespace Tests\Feature;

use App\Enumeraciones\EstadoTorneo;
use App\Enumeraciones\FormatoTorneo;
use App\Enumeraciones\Juego;
use App\Models\Partido;
use App\Models\Torneo;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Rasgos\ArmaTorneos;
use Tests\TestCase;

class BattleRoyaleTest extends TestCase
{
    use ArmaTorneos, LazilyRefreshDatabase;

    private function torneo(int $equipos = 4, int $partidas = 2): Torneo
    {
        $torneo = $this->torneoConEquipos($equipos, FormatoTorneo::BattleRoyale, ['partidas_battle_royale' => $partidas], Juego::FreeFire);

        return $this->iniciar($torneo);
    }

    /**
     * @param  list<int>  $orden  Equipos del primero al último.
     * @param  list<int>  $bajas
     */
    private function cargar(Partido $partida, array $orden, array $bajas): TestResponse
    {
        return $this->actingAs($this->organizador())->postJson("/api/v1/admin/partidos/{$partida->id}/battle-royale", [
            'resultados' => array_map(fn (int $equipo, int $indice): array => [
                'equipo_id' => $equipo,
                'posicion' => $indice + 1,
                'bajas' => $bajas[$indice],
            ], $orden, array_keys($orden)),
        ]);
    }

    public function test_al_iniciar_se_crean_las_partidas_listas_para_jugarse(): void
    {
        $torneo = $this->torneo(4, 3);

        $this->assertSame(3, $torneo->partidos()->where('estado', 'por_jugar')->count());
        $this->assertSame(0, $torneo->partidos()->whereNotNull('equipo_a_id')->count());
    }

    public function test_los_puntos_salen_de_la_tabla_del_juego_mas_uno_por_baja(): void
    {
        $torneo = $this->torneo();
        $equipos = $torneo->participantes()->pluck('equipo_id')->all();
        $partida = $torneo->partidos()->orderBy('ronda')->firstOrFail();

        $this->cargar($partida, $equipos, [5, 2, 0, 7])->assertOk();

        // Free Fire: 12, 9, 8 y 7 por puesto.
        $tabla = $this->getJson("/api/v1/torneos/{$torneo->slug}/cuadro")
            ->json('tablas.'.$torneo->fases()->firstOrFail()->id.'.general');

        $this->assertSame([17, 14, 11, 8], array_column($tabla, 'puntos'));
        $this->assertSame($equipos[0], $tabla[0]['equipo']['id']);
        $this->assertSame($equipos[3], $tabla[1]['equipo']['id']);
    }

    public function test_la_sala_tiene_que_traer_a_todos_con_puestos_sin_repetir(): void
    {
        $torneo = $this->torneo();
        $equipos = $torneo->participantes()->pluck('equipo_id')->all();
        $partida = $torneo->partidos()->firstOrFail();

        $this->cargar($partida, array_slice($equipos, 0, 3), [0, 0, 0])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Carga el puesto de cada uno de los equipos, una sola vez.');

        $this->actingAs($this->organizador())->postJson("/api/v1/admin/partidos/{$partida->id}/battle-royale", [
            'resultados' => array_map(fn (int $equipo): array => ['equipo_id' => $equipo, 'posicion' => 1, 'bajas' => 0], $equipos),
        ])->assertUnprocessable()->assertJsonPath('message', 'Los puestos van del 1 al 4, sin repetir.');
    }

    public function test_la_ultima_partida_cierra_el_torneo_con_el_que_mas_puntos_suma(): void
    {
        $torneo = $this->torneo();
        $equipos = $torneo->participantes()->pluck('equipo_id')->all();
        [$primera, $segunda] = $torneo->partidos()->orderBy('ronda')->get();

        $this->cargar($primera, $equipos, [0, 0, 0, 0])->assertOk();
        $this->cargar($segunda, array_reverse($equipos), [0, 0, 0, 20])->assertOk();

        $torneo->refresh();
        $this->assertSame(EstadoTorneo::Finalizado, $torneo->estado);

        // Primero: 12 + 7 = 19. Último: 7 + 12 = 19. Gana el de las 20 bajas.
        $this->assertSame($equipos[0], $torneo->campeon_id);
    }

    public function test_el_ranking_del_juego_suma_los_puntos_de_todas_las_partidas(): void
    {
        $torneo = $this->torneo();
        $equipos = $torneo->participantes()->pluck('equipo_id')->all();
        $this->cargar($torneo->partidos()->firstOrFail(), $equipos, [3, 0, 0, 0])->assertOk();

        $this->getJson('/api/v1/juegos/freefire')
            ->assertOk()
            ->assertJsonPath('ranking.0.equipo.id', $equipos[0])
            ->assertJsonPath('ranking.0.puntos', 15);
    }
}
