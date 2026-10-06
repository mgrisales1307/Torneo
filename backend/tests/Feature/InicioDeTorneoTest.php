<?php

namespace Tests\Feature;

use App\Enumeraciones\EstadoInscripcion;
use App\Enumeraciones\EstadoPartido;
use App\Enumeraciones\EstadoTorneo;
use App\Enumeraciones\FormatoTorneo;
use App\Enumeraciones\Juego;
use App\Models\Equipo;
use App\Models\Inscripcion;
use App\Models\Torneo;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Rasgos\ArmaTorneos;
use Tests\TestCase;

class InicioDeTorneoTest extends TestCase
{
    use ArmaTorneos, LazilyRefreshDatabase;

    public function test_la_organizacion_inicia_el_torneo_y_queda_armado_el_cuadro(): void
    {
        $torneo = $this->torneoConEquipos(8);

        $this->actingAs(Usuario::factory()->empleado()->create())
            ->postJson("/api/v1/admin/torneos/{$torneo->slug}/iniciar")
            ->assertOk()
            ->assertJsonPath('data.estado.valor', EstadoTorneo::EnCurso->value);

        $this->assertSame(7, $torneo->partidos()->count());
        $this->assertSame(4, $torneo->partidos()->where('estado', EstadoPartido::PorJugar->value)->count());
        $this->assertSame(range(1, 8), $torneo->participantes()->pluck('semilla')->all());
    }

    public function test_un_jugador_no_puede_iniciar_un_torneo(): void
    {
        $torneo = $this->torneoConEquipos(4);

        $this->actingAs(Usuario::factory()->create())
            ->postJson("/api/v1/admin/torneos/{$torneo->slug}/iniciar")
            ->assertForbidden();
    }

    public function test_con_check_in_solo_juegan_los_que_lo_hicieron(): void
    {
        $torneo = Torneo::factory()->conCheckIn()->create(['cupo_maximo_equipos' => 8]);

        foreach (range(1, 5) as $numero) {
            $this->aprobar($torneo, Equipo::factory()->completo()->create(), conCheckIn: $numero <= 4);
        }

        $sinPagar = Inscripcion::factory()->create(['torneo_id' => $torneo->id]);

        $this->iniciar($torneo);

        $this->assertSame(4, $torneo->participantes()->count());
        $this->assertSame(1, $torneo->inscripciones()->where('notas_revision', 'No hizo check-in.')->count());
        $this->assertSame(EstadoInscripcion::Retirada, $sinPagar->refresh()->estado);
    }

    public function test_no_se_inicia_con_inscripciones_por_revisar_ni_sin_equipos_suficientes(): void
    {
        $torneo = $this->torneoConEquipos(3, FormatoTorneo::EliminacionDoble);

        $this->actingAs($this->organizador())
            ->postJson("/api/v1/admin/torneos/{$torneo->slug}/iniciar")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Hacen falta al menos 4 equipos aprobados; hay 3.');

        Inscripcion::factory()->pendienteDeRevision()->create(['torneo_id' => $torneo->id]);

        $this->actingAs($this->organizador())
            ->postJson("/api/v1/admin/torneos/{$torneo->slug}/iniciar")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Hay 1 inscripción por revisar. Apruébalas o recházalas antes de iniciar.');
    }

    public function test_la_siembra_sale_del_ranking_del_juego(): void
    {
        // Un torneo previo donde el último inscrito gana todo.
        $previo = $this->torneoConEquipos(4);
        $this->iniciar($previo);
        $campeon = $previo->participantes()->get()->last()->equipo;

        while ($partido = $previo->partidos()->where('estado', 'por_jugar')->orderBy('id')->first()) {
            $this->resolver($partido, $partido->equipo_b_id === $campeon->id ? 'b' : 'a');
        }

        $this->assertSame($campeon->id, $previo->refresh()->campeon_id);

        $nuevo = $this->torneoConEquipos(3);
        $this->aprobar($nuevo, $campeon);
        $this->iniciar($nuevo);

        $this->assertSame($campeon->id, $nuevo->participantes()->firstOrFail()->equipo_id);
    }

    public function test_en_battle_royale_no_entran_mas_equipos_que_los_de_una_sala(): void
    {
        $torneo = $this->torneoConEquipos(13, FormatoTorneo::BattleRoyale, ['partidas_battle_royale' => 3], Juego::FreeFire);

        $this->actingAs($this->organizador())
            ->postJson("/api/v1/admin/torneos/{$torneo->slug}/iniciar")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'En una sala de Free Fire caben 12 equipos y hay 13.');
    }

    public function test_el_cuadro_publico_trae_las_fases_y_sus_partidos(): void
    {
        $torneo = $this->torneoConEquipos(6, FormatoTorneo::EliminacionDoble);
        $this->iniciar($torneo);

        $this->getJson("/api/v1/torneos/{$torneo->slug}/cuadro")
            ->assertOk()
            ->assertJsonPath('data.0.tipo.valor', 'eliminacion_doble')
            ->assertJsonCount($torneo->partidos()->count(), 'data.0.partidos');
    }

    public function test_un_torneo_en_borrador_no_se_ve_en_publico(): void
    {
        $torneo = Torneo::factory()->borrador()->create();

        $this->getJson("/api/v1/torneos/{$torneo->slug}")->assertForbidden();
        $this->getJson('/api/v1/torneos')->assertOk()->assertJsonCount(0, 'data');
    }
}
