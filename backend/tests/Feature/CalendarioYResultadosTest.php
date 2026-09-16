<?php

namespace Tests\Feature;

use App\Enumeraciones\EstadoPartido;
use App\Enumeraciones\TipoEventoPartido;
use App\Models\Equipo;
use App\Models\Inscripcion;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\Torneo;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class CalendarioYResultadosTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Usuario $organizador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organizador = Usuario::factory()->empleado()->create();
    }

    public function test_el_calendario_de_una_liga_enfrenta_a_todos_una_vez(): void
    {
        $torneo = Torneo::factory()->inscripcionesCerradas()->create();
        $equipos = $this->equiposInscritos($torneo, 4);

        $this->actingAs($this->organizador)
            ->postJson("/api/v1/admin/torneos/{$torneo->slug}/calendario")
            ->assertCreated()
            ->assertJsonPath('message', 'Calendario generado: 6 partidos.');

        // Cuatro equipos: 3 jornadas de 2 partidos.
        $this->assertSame(6, $torneo->partidos()->count());
        $this->assertSame(3, $torneo->partidos()->max('jornada'));

        // Cada equipo juega exactamente una vez contra cada rival.
        foreach ($equipos as $equipo) {
            $this->assertSame(3, $torneo->partidos()->delEquipo($equipo->id)->count());
        }

        $cruces = $torneo->partidos()->get()->map(
            fn (Partido $partido): string => implode('-', collect([$partido->equipo_local_id, $partido->equipo_visitante_id])->sort()->all())
        );

        $this->assertSame($cruces->unique()->count(), $cruces->count());
    }

    public function test_con_un_numero_impar_de_equipos_cada_jornada_deja_a_uno_descansando(): void
    {
        $torneo = Torneo::factory()->inscripcionesCerradas()->create();
        $this->equiposInscritos($torneo, 5);

        $this->actingAs($this->organizador)
            ->postJson("/api/v1/admin/torneos/{$torneo->slug}/calendario")
            ->assertCreated();

        // Cinco equipos: 5 jornadas de 2 partidos, uno descansa cada fecha.
        $this->assertSame(10, $torneo->partidos()->count());
        $this->assertSame(5, $torneo->partidos()->max('jornada'));
    }

    public function test_el_formato_de_ida_y_vuelta_duplica_las_jornadas(): void
    {
        $torneo = Torneo::factory()->inscripcionesCerradas()->conIdaYVuelta()->create();
        $this->equiposInscritos($torneo, 4);

        $this->actingAs($this->organizador)
            ->postJson("/api/v1/admin/torneos/{$torneo->slug}/calendario")
            ->assertCreated();

        $this->assertSame(12, $torneo->partidos()->count());
        $this->assertSame(6, $torneo->partidos()->max('jornada'));
        $this->assertSame(6, $torneo->partidos()->where('es_vuelta', true)->count());
    }

    public function test_no_se_genera_calendario_con_un_solo_equipo(): void
    {
        $torneo = Torneo::factory()->inscripcionesCerradas()->create();
        $this->equiposInscritos($torneo, 1);

        $this->actingAs($this->organizador)
            ->postJson("/api/v1/admin/torneos/{$torneo->slug}/calendario")
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Hacen falta al menos dos equipos con la inscripción aprobada para generar el calendario.'
            );
    }

    public function test_no_se_regenera_el_calendario_sin_pedirlo_explicitamente(): void
    {
        $torneo = Torneo::factory()->inscripcionesCerradas()->create();
        $this->equiposInscritos($torneo, 4);

        $this->actingAs($this->organizador)->postJson("/api/v1/admin/torneos/{$torneo->slug}/calendario")->assertCreated();

        $this->actingAs($this->organizador)
            ->postJson("/api/v1/admin/torneos/{$torneo->slug}/calendario")
            ->assertUnprocessable();

        $this->actingAs($this->organizador)
            ->postJson("/api/v1/admin/torneos/{$torneo->slug}/calendario", ['reiniciar' => true])
            ->assertCreated();

        $this->assertSame(6, $torneo->partidos()->count());
    }

    public function test_no_se_borra_un_calendario_que_ya_tiene_resultados(): void
    {
        $torneo = Torneo::factory()->enCurso()->create();
        $equipos = $this->equiposInscritos($torneo, 4);

        $this->actingAs($this->organizador)->postJson("/api/v1/admin/torneos/{$torneo->slug}/calendario")->assertCreated();

        $torneo->partidos()->first()->forceFill([
            'estado' => EstadoPartido::Finalizado,
            'goles_local' => 1,
            'goles_visitante' => 0,
        ])->save();

        $this->actingAs($this->organizador)
            ->postJson("/api/v1/admin/torneos/{$torneo->slug}/calendario", ['reiniciar' => true])
            ->assertUnprocessable();

        $this->assertSame(6, $torneo->partidos()->count());
    }

    public function test_un_dueno_no_puede_generar_el_calendario(): void
    {
        $torneo = Torneo::factory()->inscripcionesCerradas()->create();
        $this->equiposInscritos($torneo, 4);

        $this->actingAs(Usuario::factory()->dueno()->create())
            ->postJson("/api/v1/admin/torneos/{$torneo->slug}/calendario")
            ->assertForbidden();

        $this->assertSame(0, $torneo->partidos()->count());
    }

    public function test_se_registra_el_resultado_con_sus_goleadores_y_se_actualiza_la_tabla(): void
    {
        $torneo = Torneo::factory()->enCurso()->create();
        [$local, $visitante] = $this->equiposInscritos($torneo, 2)->all();

        $delantero = Jugador::factory()->delEquipo($local)->conDorsal(9)->create();
        $mediocampista = Jugador::factory()->delEquipo($local)->conDorsal(8)->create();
        $rival = Jugador::factory()->delEquipo($visitante)->conDorsal(10)->create();

        $partido = Partido::factory()->delTorneo($torneo)->entre($local, $visitante)->create();

        $this->actingAs($this->organizador)->postJson("/api/v1/admin/partidos/{$partido->id}/resultado", [
            'goles_local' => 2,
            'goles_visitante' => 1,
            'eventos' => [
                ['equipo_id' => $local->id, 'jugador_id' => $delantero->id, 'tipo' => TipoEventoPartido::Gol->value, 'minuto' => 12],
                ['equipo_id' => $local->id, 'jugador_id' => $mediocampista->id, 'tipo' => TipoEventoPartido::GolDePenal->value, 'minuto' => 44],
                ['equipo_id' => $visitante->id, 'jugador_id' => $rival->id, 'tipo' => TipoEventoPartido::Gol->value, 'minuto' => 80],
            ],
        ])->assertOk()->assertJsonPath('data.estado.valor', EstadoPartido::Finalizado->value);

        $partido->refresh();
        $this->assertSame(2, $partido->goles_local);
        $this->assertSame(1, $partido->goles_visitante);
        $this->assertSame(3, $partido->eventos()->count());

        $tabla = $this->getJson("/api/v1/torneos/{$torneo->slug}/tabla")->json('data');
        $this->assertSame($local->nombre, $tabla[0]['equipo']['nombre']);
        $this->assertSame(3, $tabla[0]['puntos']);
        $this->assertSame(0, $tabla[1]['puntos']);
    }

    public function test_el_marcador_tiene_que_cuadrar_con_los_goles_registrados(): void
    {
        $torneo = Torneo::factory()->enCurso()->create();
        [$local, $visitante] = $this->equiposInscritos($torneo, 2)->all();
        $delantero = Jugador::factory()->delEquipo($local)->conDorsal(9)->create();
        $partido = Partido::factory()->delTorneo($torneo)->entre($local, $visitante)->create();

        $this->actingAs($this->organizador)->postJson("/api/v1/admin/partidos/{$partido->id}/resultado", [
            'goles_local' => 3,
            'goles_visitante' => 0,
            'eventos' => [
                ['equipo_id' => $local->id, 'jugador_id' => $delantero->id, 'tipo' => TipoEventoPartido::Gol->value],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('eventos');

        $this->assertFalse($partido->refresh()->estaFinalizado());
        $this->assertSame(0, $partido->eventos()->count());
    }

    public function test_el_autogol_le_suma_al_rival(): void
    {
        $torneo = Torneo::factory()->enCurso()->create();
        [$local, $visitante] = $this->equiposInscritos($torneo, 2)->all();
        $defensa = Jugador::factory()->delEquipo($visitante)->conDorsal(2)->create();
        $partido = Partido::factory()->delTorneo($torneo)->entre($local, $visitante)->create();

        $this->actingAs($this->organizador)->postJson("/api/v1/admin/partidos/{$partido->id}/resultado", [
            'goles_local' => 1,
            'goles_visitante' => 0,
            'eventos' => [
                ['equipo_id' => $visitante->id, 'jugador_id' => $defensa->id, 'tipo' => TipoEventoPartido::Autogol->value, 'minuto' => 30],
            ],
        ])->assertOk();

        $this->assertSame(1, $partido->refresh()->goles_local);

        // Y no cuenta como gol suyo en la tabla de goleadores.
        $this->assertEmpty($this->getJson("/api/v1/torneos/{$torneo->slug}/goleadores")->json('data'));
    }

    public function test_no_se_registra_un_gol_de_un_jugador_que_no_juega_ese_partido(): void
    {
        $torneo = Torneo::factory()->enCurso()->create();
        [$local, $visitante] = $this->equiposInscritos($torneo, 2)->all();
        $ajeno = Jugador::factory()->delEquipo(Equipo::factory()->create())->conDorsal(7)->create();
        $partido = Partido::factory()->delTorneo($torneo)->entre($local, $visitante)->create();

        $this->actingAs($this->organizador)->postJson("/api/v1/admin/partidos/{$partido->id}/resultado", [
            'goles_local' => 1,
            'goles_visitante' => 0,
            'eventos' => [
                ['equipo_id' => $local->id, 'jugador_id' => $ajeno->id, 'tipo' => TipoEventoPartido::Gol->value],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('eventos');

        $this->assertFalse($partido->refresh()->estaFinalizado());
    }

    public function test_corregir_un_resultado_reemplaza_los_eventos_anteriores(): void
    {
        $torneo = Torneo::factory()->enCurso()->create();
        [$local, $visitante] = $this->equiposInscritos($torneo, 2)->all();
        $delantero = Jugador::factory()->delEquipo($local)->conDorsal(9)->create();
        $partido = Partido::factory()->delTorneo($torneo)->entre($local, $visitante)->create();

        $unGol = [
            'goles_local' => 1,
            'goles_visitante' => 0,
            'eventos' => [
                ['equipo_id' => $local->id, 'jugador_id' => $delantero->id, 'tipo' => TipoEventoPartido::Gol->value],
            ],
        ];

        $this->actingAs($this->organizador)
            ->postJson("/api/v1/admin/partidos/{$partido->id}/resultado", $unGol)
            ->assertOk();

        $this->actingAs($this->organizador)
            ->postJson("/api/v1/admin/partidos/{$partido->id}/resultado", [...$unGol, 'goles_local' => 0, 'eventos' => []])
            ->assertOk();

        $this->assertSame(0, $partido->refresh()->eventos()->count());
        $this->assertSame(0, $partido->goles_local);
        $this->assertEmpty($this->getJson("/api/v1/torneos/{$torneo->slug}/goleadores")->json('data'));
    }

    public function test_reprogramar_un_partido_finalizado_le_quita_el_marcador(): void
    {
        $torneo = Torneo::factory()->enCurso()->create();
        [$local, $visitante] = $this->equiposInscritos($torneo, 2)->all();
        $partido = Partido::factory()->delTorneo($torneo)->entre($local, $visitante)->finalizado(2, 0)->create();

        $this->actingAs($this->organizador)->putJson("/api/v1/admin/partidos/{$partido->id}", [
            'estado' => EstadoPartido::Aplazado->value,
            'programado_para' => now()->addWeeks(2)->toIso8601String(),
        ])->assertOk()->assertJsonPath('data.marcador', null);

        $partido->refresh();
        $this->assertNull($partido->goles_local);
        $this->assertSame(EstadoPartido::Aplazado, $partido->estado);

        $tabla = $this->getJson("/api/v1/torneos/{$torneo->slug}/tabla")->json('data');
        $this->assertSame(0, $tabla[0]['partidos_jugados']);
    }

    public function test_no_se_puede_dar_por_finalizado_un_partido_sin_cargar_el_resultado(): void
    {
        $torneo = Torneo::factory()->enCurso()->create();
        [$local, $visitante] = $this->equiposInscritos($torneo, 2)->all();
        $partido = Partido::factory()->delTorneo($torneo)->entre($local, $visitante)->create();

        $this->actingAs($this->organizador)->putJson("/api/v1/admin/partidos/{$partido->id}", [
            'estado' => EstadoPartido::Finalizado->value,
        ])->assertUnprocessable()->assertJsonValidationErrors('estado');
    }

    /**
     * @return Collection<int, Equipo>
     */
    private function equiposInscritos(Torneo $torneo, int $cantidad): Collection
    {
        return collect(range(1, $cantidad))->map(function () use ($torneo): Equipo {
            $equipo = Equipo::factory()->create();

            Inscripcion::factory()->aprobada()->create([
                'torneo_id' => $torneo->getKey(),
                'equipo_id' => $equipo->getKey(),
            ]);

            return $equipo;
        });
    }
}
