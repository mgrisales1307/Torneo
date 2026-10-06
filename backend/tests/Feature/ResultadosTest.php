<?php

namespace Tests\Feature;

use App\Enumeraciones\EstadoFase;
use App\Enumeraciones\EstadoPartido;
use App\Enumeraciones\EstadoTorneo;
use App\Enumeraciones\FormatoTorneo;
use App\Enumeraciones\LlaveDelCuadro;
use App\Enumeraciones\TipoDeFase;
use App\Models\Partido;
use App\Models\Torneo;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Rasgos\ArmaTorneos;
use Tests\Rasgos\CreaImagenesDePrueba;
use Tests\TestCase;

class ResultadosTest extends TestCase
{
    use ArmaTorneos, CreaImagenesDePrueba, LazilyRefreshDatabase;

    private function primerPartido(Torneo $torneo): Partido
    {
        return $torneo->partidos()->where('estado', EstadoPartido::PorJugar->value)->orderBy('id')->firstOrFail();
    }

    private function reportar(Partido $partido, string $lado, int $victoriasA, int $victoriasB): TestResponse
    {
        $capitan = ($lado === 'a' ? $partido->equipoA : $partido->equipoB)->capitan;

        return $this->actingAs($capitan)->post("/api/v1/panel/partidos/{$partido->id}/reporte", [
            'victorias_a' => $victoriasA,
            'victorias_b' => $victoriasB,
            'captura' => $this->imagenDePrueba('final.png', 1280, 720),
        ], ['Accept' => 'application/json']);
    }

    public function test_si_los_dos_capitanes_reportan_lo_mismo_el_partido_se_cierra_y_el_ganador_avanza(): void
    {
        $torneo = $this->torneoConEquipos(4);
        $this->iniciar($torneo);
        $partido = $this->primerPartido($torneo);

        $this->reportar($partido, 'a', 1, 0)
            ->assertOk()
            ->assertJsonPath('data.estado.valor', EstadoPartido::Reportado->value)
            ->assertJsonPath('message', 'Resultado reportado. Falta que el rival lo confirme.');

        $this->reportar($partido, 'b', 1, 0)
            ->assertOk()
            ->assertJsonPath('data.estado.valor', EstadoPartido::Finalizado->value);

        $partido->refresh();
        $this->assertSame($partido->equipo_a_id, $partido->ganador_id);
        $this->assertSame($partido->equipo_a_id, Partido::query()->findOrFail($partido->siguiente_ganador_id)->{"equipo_{$partido->siguiente_ganador_ranura}_id"});
    }

    public function test_si_los_reportes_no_coinciden_queda_en_disputa_y_la_organizacion_decide(): void
    {
        $torneo = $this->torneoConEquipos(4);
        $this->iniciar($torneo);
        $partido = $this->primerPartido($torneo);

        $this->reportar($partido, 'a', 1, 0)->assertOk();
        $this->reportar($partido, 'b', 0, 1)
            ->assertOk()
            ->assertJsonPath('data.estado.valor', EstadoPartido::EnDisputa->value);

        // La organización ve las dos capturas.
        $this->actingAs($this->organizador())->getJson('/api/v1/admin/partidos')
            ->assertOk()
            ->assertJsonPath('data.0.id', $partido->id)
            ->assertJsonCount(2, 'data.0.reportes');

        $this->actingAs($this->organizador())
            ->postJson("/api/v1/admin/partidos/{$partido->id}/resultado", ['victorias_a' => 0, 'victorias_b' => 1])
            ->assertOk()
            ->assertJsonPath('data.estado.valor', EstadoPartido::Finalizado->value)
            ->assertJsonPath('data.ganador_id', $partido->equipo_b_id);
    }

    public function test_solo_reportan_los_capitanes_de_los_dos_equipos(): void
    {
        $torneo = $this->torneoConEquipos(4);
        $this->iniciar($torneo);
        $partido = $this->primerPartido($torneo);
        $miembro = $partido->equipoA->miembros()->whereKeyNot($partido->equipoA->capitan_id)->firstOrFail();

        foreach ([$miembro, Usuario::factory()->create()] as $usuario) {
            $this->actingAs($usuario)
                ->postJson("/api/v1/panel/partidos/{$partido->id}/reporte", ['victorias_a' => 1, 'victorias_b' => 0])
                ->assertForbidden();
        }
    }

    public function test_el_marcador_tiene_que_ser_posible_para_la_serie(): void
    {
        $torneo = $this->torneoConEquipos(4, atributos: ['mejor_de' => 3]);
        $this->iniciar($torneo);
        $partido = $this->primerPartido($torneo);

        foreach ([[1, 1], [3, 0], [2, 2], [1, 0]] as [$a, $b]) {
            $this->reportar($partido, 'a', $a, $b)
                ->assertUnprocessable()
                ->assertJsonPath('message', 'Al mejor de 3, el ganador suma 2 y el perdedor menos de 2.');
        }

        $this->reportar($partido, 'a', 2, 1)->assertOk();
    }

    public function test_el_detalle_de_mapas_tiene_que_cuadrar_con_el_marcador(): void
    {
        $torneo = $this->torneoConEquipos(4, atributos: ['mejor_de' => 3]);
        $this->iniciar($torneo);
        $partido = $this->primerPartido($torneo);

        $this->actingAs($this->organizador())->postJson("/api/v1/admin/partidos/{$partido->id}/resultado", [
            'victorias_a' => 2,
            'victorias_b' => 0,
            'detalle' => [['mapa' => 'Ascent', 'ganador' => 'a'], ['mapa' => 'Bind', 'ganador' => 'b']],
        ])->assertUnprocessable()->assertJsonValidationErrors('detalle');

        $this->actingAs($this->organizador())->postJson("/api/v1/admin/partidos/{$partido->id}/resultado", [
            'victorias_a' => 2,
            'victorias_b' => 1,
            'detalle' => [['mapa' => 'Ascent', 'ganador' => 'a'], ['mapa' => 'Bind', 'ganador' => 'b'], ['mapa' => 'Lotus', 'ganador' => 'a']],
        ])->assertOk()->assertJsonPath('data.detalle.2.mapa', 'Lotus');
    }

    public function test_la_final_corona_al_campeon_y_cierra_el_torneo(): void
    {
        $torneo = $this->torneoConEquipos(5);
        $this->iniciar($torneo);
        $this->jugarTodo($torneo);

        $torneo->refresh();
        $this->assertSame(EstadoTorneo::Finalizado, $torneo->estado);
        $this->assertSame($torneo->participantes()->firstOrFail()->equipo_id, $torneo->campeon_id);
        $this->assertSame(3, $torneo->partidos()->where('mejor_de', 3)->firstOrFail()->mejor_de);
    }

    public function test_en_la_doble_el_que_pierde_arriba_sigue_vivo_abajo(): void
    {
        $torneo = $this->torneoConEquipos(4, FormatoTorneo::EliminacionDoble);
        $this->iniciar($torneo);
        $partido = $this->primerPartido($torneo);

        $this->resolver($partido, 'a');

        $inferior = $torneo->partidos()->where('llave', LlaveDelCuadro::Inferior->value)->orderBy('ronda')->firstOrFail();
        $this->assertTrue($inferior->participa($partido->equipo_b_id));

        $this->jugarTodo($torneo);

        $this->assertSame(EstadoTorneo::Finalizado, $torneo->refresh()->estado);
        $this->assertSame(6, $torneo->partidos()->finalizados()->count());
    }

    public function test_no_se_corrige_un_resultado_si_el_partido_siguiente_ya_se_jugo(): void
    {
        $torneo = $this->torneoConEquipos(4);
        $this->iniciar($torneo);
        [$uno, $dos] = $torneo->partidos()->where('ronda', 1)->orderBy('id')->get();

        $this->resolver($uno);

        // Corregir antes de jugar la final sí se puede: el ganador cambia en
        // el partido siguiente.
        $this->resolver($uno, 'b');
        $final = Partido::query()->findOrFail($uno->siguiente_ganador_id);
        $this->assertTrue($final->participa($uno->equipo_b_id));

        $this->resolver($dos);
        $this->resolver($final->refresh());

        // La final ya está jugada: corregir la semifinal rompería el cuadro.
        $this->actingAs($this->organizador())
            ->postJson("/api/v1/admin/partidos/{$uno->id}/resultado", ['victorias_a' => 1, 'victorias_b' => 0])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'El partido siguiente ya se jugó: corrige primero ese resultado.');
    }

    public function test_corregir_la_final_reabre_el_torneo_y_cambia_al_campeon(): void
    {
        $torneo = $this->torneoConEquipos(2);
        $this->iniciar($torneo);
        $final = $this->primerPartido($torneo);

        $this->resolver($final, 'a');
        $this->assertSame($final->equipo_a_id, $torneo->refresh()->campeon_id);

        $this->resolver($final, 'b');
        $this->assertSame($final->equipo_b_id, $torneo->refresh()->campeon_id);
        $this->assertSame(EstadoTorneo::Finalizado, $torneo->estado);
    }

    public function test_al_terminar_los_grupos_se_arman_las_eliminatorias_con_los_clasificados(): void
    {
        $torneo = $this->torneoConEquipos(8, FormatoTorneo::GruposYEliminacion, [
            'cantidad_de_grupos' => 2,
            'clasifican_por_grupo' => 2,
        ]);
        $this->iniciar($torneo);

        $grupos = $torneo->fases()->firstOrFail();
        $this->assertSame(12, $grupos->partidos()->count());

        while ($partido = $grupos->partidos()->where('estado', 'por_jugar')->orderBy('id')->first()) {
            $this->resolver($partido);
        }

        $this->assertSame(EstadoFase::Finalizada, $grupos->refresh()->estado);

        $eliminatorias = $torneo->fases()->where('orden', 2)->firstOrFail();
        $this->assertSame(TipoDeFase::EliminacionSimple, $eliminatorias->tipo);
        $this->assertSame(3, $eliminatorias->partidos()->count());

        // El primero de cada grupo cruza con el segundo del otro.
        $tablas = $this->getJson("/api/v1/torneos/{$torneo->slug}/cuadro")->assertOk()->json("tablas.{$grupos->id}");
        $semifinales = $eliminatorias->partidos()->where('ronda', 1)->get();

        $this->assertTrue($semifinales->contains(fn (Partido $partido): bool => $partido->participa($tablas['A'][0]['equipo']['id']) && $partido->participa($tablas['B'][1]['equipo']['id'])));
    }

    public function test_el_suizo_empareja_la_ronda_siguiente_sin_repetir_rivales(): void
    {
        $torneo = $this->torneoConEquipos(8, FormatoTorneo::Suizo, ['rondas_suizo' => 3]);
        $this->iniciar($torneo);

        $this->jugarTodo($torneo);

        $partidos = $torneo->partidos()->get();
        $this->assertSame(12, $partidos->count());

        $cruces = $partidos->map(fn (Partido $partido): string => min($partido->equipo_a_id, $partido->equipo_b_id).'-'.max($partido->equipo_a_id, $partido->equipo_b_id));
        $this->assertCount(12, $cruces->unique());

        $torneo->refresh();
        $this->assertSame(EstadoTorneo::Finalizado, $torneo->estado);

        $tabla = $this->getJson("/api/v1/torneos/{$torneo->slug}/cuadro")->json('tablas.'.$torneo->fases()->firstOrFail()->id.'.general');
        $this->assertSame(3, $tabla[0]['ganados']);
        $this->assertSame($tabla[0]['equipo']['id'], $torneo->campeon_id);
    }

    public function test_los_reportes_solo_los_ven_los_dos_equipos_y_la_organizacion(): void
    {
        $torneo = $this->torneoConEquipos(4);
        $this->iniciar($torneo);
        $partido = $this->primerPartido($torneo);
        $this->reportar($partido, 'a', 1, 0)->assertOk();

        // Visita anónima: se olvida la sesión del capitán que reportó.
        $this->app['auth']->forgetGuards();

        $this->getJson("/api/v1/partidos/{$partido->id}")->assertOk()->assertJsonMissingPath('data.reportes');

        $this->actingAs($partido->equipoB->capitan)->getJson("/api/v1/partidos/{$partido->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.reportes')
            ->assertJsonPath('puedo_reportar', true);
    }

    public function test_el_panel_lista_los_partidos_pendientes_de_mis_equipos(): void
    {
        $torneo = $this->torneoConEquipos(4);
        $this->iniciar($torneo);
        $partido = $this->primerPartido($torneo);
        $jugador = $partido->equipoA->miembros()->firstOrFail();

        $this->actingAs($jugador)->getJson('/api/v1/panel/partidos')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $partido->id);
    }
}
