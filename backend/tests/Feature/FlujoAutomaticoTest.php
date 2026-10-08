<?php

namespace Tests\Feature;

use App\Enumeraciones\FormatoTorneo;
use App\Enumeraciones\Juego;
use App\Models\Equipo;
use App\Models\Inscripcion;
use App\Models\Torneo;
use App\Models\Usuario;
use App\Servicios\Carteras\GestorDeCarteras;
use Database\Seeders\ExposicionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Rasgos\ArmaTorneos;
use Tests\TestCase;

class FlujoAutomaticoTest extends TestCase
{
    use ArmaTorneos, LazilyRefreshDatabase;

    private function torneoAutomatico(array $datos = []): Torneo
    {
        return Torneo::factory()->create(['flujo_automatico' => true, 'modalidad' => 'individual', 'cupo_maximo_equipos' => 2,
            'cuenta_regresiva_segundos' => 0, 'mejor_de_final' => 1, 'premios' => [1_000_000, 500_000], ...$datos]);
    }

    private function inscribirYpagar(Torneo $torneo, Usuario $usuario): Inscripcion
    {
        $respuesta = $this->actingAs($usuario)->postJson("/api/v1/panel/torneos/{$torneo->slug}/inscripciones")->assertCreated();
        $id = $respuesta->json('data.id');
        $referencia = $this->postJson("/api/v1/panel/inscripciones/{$id}/orden")->assertCreated()->json('data.referencia');
        $this->postJson("/api/v1/panel/ordenes/{$referencia}/pagar-simulado", ['resultado' => 'aprobado'])
            ->assertOk()->assertJsonPath('data.inscripcion.estado.valor', 'aprobada');

        return Inscripcion::findOrFail($id);
    }

    private function individualCompleto(array $datos = []): Torneo
    {
        $torneo = $this->torneoAutomatico($datos);
        for ($i = 0; $i < $torneo->cupo_maximo_equipos; $i++) {
            $this->inscribirYpagar($torneo, Usuario::factory()->conCuentas($torneo->juego)->create());
        }

        return $torneo->refresh();
    }

    public function test_crear_publica_un_torneo_automatico_sin_revision_ni_check_in(): void
    {
        $admin = Usuario::factory()->administrador()->create();

        $respuesta = $this->actingAs($admin)->postJson('/api/v1/admin/torneos', [
            'juego' => 'valorant', 'nombre' => 'Copa exposición', 'formato' => 'eliminacion_simple',
            'modalidad' => 'individual', 'cupo_maximo_equipos' => 4, 'costo_inscripcion_centavos' => 100000,
        ])->assertCreated()->assertJsonPath('data.estado.valor', 'inscripciones_abiertas')
            ->assertJsonPath('data.flujo_automatico', true)->assertJsonPath('data.check_in.minutos', 0);

        $this->assertDatabaseHas('torneos', ['slug' => $respuesta->json('data.slug'), 'flujo_automatico' => 1]);
    }

    public function test_el_inicio_exige_cupo_completo_y_cuenta_regresiva_terminada(): void
    {
        $this->freezeTime();
        $torneo = $this->torneoAutomatico(['cuenta_regresiva_segundos' => 60]);
        $admin = Usuario::factory()->administrador()->create();
        $this->inscribirYpagar($torneo, Usuario::factory()->conCuentas(Juego::Valorant)->create());
        $this->actingAs($admin)->postJson("/api/v1/admin/torneos/{$torneo->slug}/iniciar")->assertUnprocessable();
        $this->assertSame(0, $torneo->partidos()->count());
        $this->inscribirYpagar($torneo, Usuario::factory()->conCuentas(Juego::Valorant)->create());
        $this->actingAs($admin)->postJson("/api/v1/admin/torneos/{$torneo->slug}/iniciar")->assertUnprocessable();
        $this->travel(61)->seconds();

        $this->postJson("/api/v1/admin/torneos/{$torneo->slug}/iniciar")->assertOk()->assertJsonPath('data.estado.valor', 'en_curso');

        $this->assertSame(1, $torneo->partidos()->count());
    }

    public function test_el_pago_tardio_no_sobrevende_el_ultimo_cupo(): void
    {
        $torneo = $this->torneoAutomatico();
        $tardio = Usuario::factory()->conCuentas(Juego::Valorant)->create();
        $id = $this->actingAs($tardio)->postJson("/api/v1/panel/torneos/{$torneo->slug}/inscripciones")->assertCreated()->json('data.id');
        $ref = $this->postJson("/api/v1/panel/inscripciones/{$id}/orden")->assertCreated()->json('data.referencia');
        $this->inscribirYpagar($torneo, Usuario::factory()->conCuentas(Juego::Valorant)->create());
        $this->inscribirYpagar($torneo, Usuario::factory()->conCuentas(Juego::Valorant)->create());

        $this->actingAs($tardio)->postJson("/api/v1/panel/ordenes/{$ref}/pagar-simulado", ['resultado' => 'aprobado'])->assertUnprocessable();

        $this->assertSame(2, $torneo->inscripciones()->aprobadas()->count());
        $this->assertDatabaseHas('inscripciones', ['id' => $id, 'estado' => 'pendiente_pago']);
        $this->assertDatabaseHas('ordenes', ['referencia' => $ref, 'estado' => 'pendiente']);
    }

    public function test_la_final_acredita_premios_y_repetir_el_marcador_no_duplica_saldos(): void
    {
        $torneo = $this->individualCompleto();
        $this->iniciar($torneo);
        $final = $torneo->partidos()->firstOrFail();
        $ganador = $torneo->inscripciones()->where('equipo_id', $final->equipo_a_id)->firstOrFail()->usuario_inscriptor_id;
        $segundo = $torneo->inscripciones()->where('equipo_id', $final->equipo_b_id)->firstOrFail()->usuario_inscriptor_id;

        $this->resolver($final);
        $this->resolver($final);

        $this->assertDatabaseHas('carteras', ['usuario_id' => $ganador, 'saldo_centavos' => 1_000_000]);
        $this->assertDatabaseHas('carteras', ['usuario_id' => $segundo, 'saldo_centavos' => 500_000]);
        $this->assertSame(2, DB::table('movimientos_cartera')->where('tipo', 'premio')->count());
        $this->assertSame('finalizado', $torneo->refresh()->estado->value);
        $this->getJson("/api/v1/torneos/{$torneo->slug}")->assertOk()->assertJsonPath('data.clasificacion_final.0.equipo.id', $final->equipo_a_id);
    }

    public function test_pagar_con_cartera_descuenta_una_sola_vez_y_confirma_la_inscripcion(): void
    {
        $usuario = Usuario::factory()->conCuentas(Juego::Valorant)->create();
        app(GestorDeCarteras::class)->mover($usuario->id, 'COP', 6_000_000, 'premio', 'Premio anterior', 'prueba-premio');
        $torneo = $this->torneoAutomatico();
        $id = $this->actingAs($usuario)->postJson("/api/v1/panel/torneos/{$torneo->slug}/inscripciones")->assertCreated()->json('data.id');

        $this->postJson("/api/v1/panel/inscripciones/{$id}/pagar-cartera")->assertOk()->assertJsonPath('data.estado.valor', 'pagada');
        $this->postJson("/api/v1/panel/inscripciones/{$id}/pagar-cartera")->assertOk();

        $this->assertDatabaseHas('carteras', ['usuario_id' => $usuario->id, 'saldo_centavos' => 1_000_000]);
        $this->assertDatabaseHas('inscripciones', ['id' => $id, 'estado' => 'aprobada']);
        $this->assertSame(1, DB::table('movimientos_cartera')->where('tipo', 'inscripcion')->count());
    }

    public function test_saldo_insuficiente_no_confirma_inscripcion_ni_genera_debito(): void
    {
        $usuario = Usuario::factory()->conCuentas(Juego::Valorant)->create();
        $torneo = $this->torneoAutomatico();
        $id = $this->actingAs($usuario)->postJson("/api/v1/panel/torneos/{$torneo->slug}/inscripciones")->assertCreated()->json('data.id');

        $this->postJson("/api/v1/panel/inscripciones/{$id}/pagar-cartera")->assertUnprocessable();

        $this->assertDatabaseHas('inscripciones', ['id' => $id, 'estado' => 'pendiente_pago']);
        $this->assertSame(0, DB::table('movimientos_cartera')->count());
    }

    public function test_retirarse_devuelve_el_saldo_y_reinicia_la_cuenta_regresiva(): void
    {
        $this->freezeTime();
        $torneo = $this->individualCompleto(['cuenta_regresiva_segundos' => 60]);
        $inscripcion = $torneo->participantes()->first() ?? $torneo->inscripciones()->aprobadas()->firstOrFail();
        $usuario = Usuario::findOrFail($inscripcion->usuario_inscriptor_id);
        $this->assertNotNull($torneo->lleno_en);

        $this->actingAs($usuario)->deleteJson("/api/v1/panel/inscripciones/{$inscripcion->id}")->assertOk();

        $this->assertNull($torneo->refresh()->lleno_en);
        $this->assertDatabaseHas('carteras', ['usuario_id' => $usuario->id, 'saldo_centavos' => 5_000_000]);
        $this->deleteJson("/api/v1/panel/inscripciones/{$inscripcion->id}")->assertUnprocessable();
        $this->assertSame(1, DB::table('movimientos_cartera')->where('tipo', 'devolucion')->count());
    }

    public function test_cancelar_devuelve_todas_las_inscripciones_sin_pagos_posteriores(): void
    {
        $torneo = $this->individualCompleto();
        $admin = Usuario::factory()->administrador()->create();

        $this->actingAs($admin)->postJson("/api/v1/admin/torneos/{$torneo->slug}/estado", ['estado' => 'cancelado'])->assertOk();

        $this->assertSame(2, $torneo->inscripciones()->where('estado', 'retirada')->count());
        $this->assertSame(2, DB::table('movimientos_cartera')->where('tipo', 'devolucion')->count());
    }

    public function test_los_retiros_son_idempotentes_y_no_permiten_saldo_negativo(): void
    {
        $usuario = Usuario::factory()->create();
        app(GestorDeCarteras::class)->mover($usuario->id, 'COP', 100_000, 'premio', 'Premio', 'prueba-retiro');
        $datos = ['monto_centavos' => 60_000, 'referencia' => (string) Str::uuid()];

        $this->actingAs($usuario)->postJson('/api/v1/panel/cartera/retiros', $datos)->assertOk();
        $this->postJson('/api/v1/panel/cartera/retiros', $datos)->assertOk();
        $this->postJson('/api/v1/panel/cartera/retiros', ['monto_centavos' => 60_000, 'referencia' => (string) Str::uuid()])->assertUnprocessable();

        $this->getJson('/api/v1/panel/cartera')->assertOk()->assertJsonPath('saldo_centavos', 40_000);
        $this->assertSame(1, DB::table('movimientos_cartera')->where('tipo', 'retiro')->count());
        $this->actingAs(Usuario::factory()->create())->getJson('/api/v1/panel/cartera')->assertOk()->assertJsonPath('saldo_centavos', 0)->assertJsonCount(0, 'movimientos.data');
    }

    public function test_cartera_y_retiros_exigen_autenticacion_y_montos_validos(): void
    {
        $this->getJson('/api/v1/panel/cartera')->assertUnauthorized();
        $this->postJson('/api/v1/panel/cartera/retiros')->assertUnauthorized();
        $this->actingAs(Usuario::factory()->create())->postJson('/api/v1/panel/cartera/retiros', ['monto_centavos' => -100, 'referencia' => 'invalida'])
            ->assertUnprocessable()->assertJsonValidationErrors(['monto_centavos', 'referencia']);
        $this->assertSame(0, DB::table('movimientos_cartera')->count());
    }

    public function test_solo_la_organizacion_puede_cargar_marcadores_automaticos(): void
    {
        $torneo = $this->individualCompleto();
        $this->iniciar($torneo);
        $partido = $torneo->partidos()->firstOrFail();
        $usuario = $partido->equipoA->capitan;

        $this->actingAs($usuario)->postJson("/api/v1/panel/partidos/{$partido->id}/reporte", ['victorias_a' => 1, 'victorias_b' => 0])->assertForbidden();
        $this->postJson("/api/v1/admin/partidos/{$partido->id}/resultado", ['victorias_a' => 1, 'victorias_b' => 0])->assertForbidden();

        $this->assertSame('por_jugar', $partido->refresh()->estado->value);
    }

    public function test_corregir_la_final_reconcilia_premios_sin_crear_dinero(): void
    {
        $torneo = $this->individualCompleto();
        $this->iniciar($torneo);
        $final = $torneo->partidos()->firstOrFail();
        $this->resolver($final);

        $this->resolver($final, 'b');

        $this->assertSame($final->equipo_b_id, $torneo->refresh()->campeon_id);
        $this->assertSame(1_500_000, (int) DB::table('carteras')->sum('saldo_centavos'));
        $this->assertDatabaseHas('carteras', ['usuario_id' => $final->equipoB->capitan_id, 'saldo_centavos' => 1_000_000]);
    }

    public function test_no_se_cambia_un_premio_que_ya_fue_retirado_y_se_restaura_el_resultado(): void
    {
        $torneo = $this->individualCompleto();
        $this->iniciar($torneo);
        $final = $torneo->partidos()->firstOrFail();
        $this->resolver($final);
        $usuario = $final->equipoA->capitan;
        $this->actingAs($usuario)->postJson('/api/v1/panel/cartera/retiros', ['monto_centavos' => 1_000_000, 'referencia' => (string) Str::uuid()])->assertOk();

        $this->actingAs($this->organizador())->postJson("/api/v1/admin/partidos/{$final->id}/resultado", ['victorias_a' => 0, 'victorias_b' => 1])->assertUnprocessable();

        $this->assertSame($final->equipo_a_id, $torneo->refresh()->campeon_id);
        $this->assertSame($final->equipo_a_id, $final->refresh()->ganador_id);
        $this->assertSame('finalizado', $torneo->estado->value);
    }

    public function test_la_eliminacion_doble_crea_revancha_si_gana_la_llave_inferior(): void
    {
        $torneo = $this->individualCompleto(['formato' => FormatoTorneo::EliminacionDoble, 'cupo_maximo_equipos' => 4]);
        $this->iniciar($torneo);
        while ($partido = $torneo->partidos()->where('estado', 'por_jugar')->where('llave', '!=', 'gran_final')->orderBy('id')->first()) {
            $this->resolver($partido);
        }
        $final = $torneo->partidos()->where('llave', 'gran_final')->firstOrFail();

        $this->resolver($final, 'b');

        $this->assertSame('en_curso', $torneo->refresh()->estado->value);
        $this->assertSame(0, DB::table('premios_entregados')->count());
        $revancha = $torneo->partidos()->where('llave', 'gran_final')->where('ronda', 2)->firstOrFail();
        $this->resolver($revancha, 'b');
        $this->assertSame('finalizado', $torneo->refresh()->estado->value);
        $this->assertSame($final->equipo_b_id, $torneo->campeon_id);
        $this->assertSame(2, DB::table('premios_entregados')->count());
    }

    public function test_el_tercer_puesto_se_juega_antes_de_entregar_los_tres_premios(): void
    {
        $torneo = $this->individualCompleto(['cupo_maximo_equipos' => 4, 'premios' => [1_000_000, 500_000, 250_000]]);
        $this->iniciar($torneo);
        while ($partido = $torneo->partidos()->where('estado', 'por_jugar')->where('llave', 'superior')->orderBy('id')->first()) {
            $this->resolver($partido);
        }
        $this->assertSame('en_curso', $torneo->refresh()->estado->value);
        $this->assertSame(0, DB::table('premios_entregados')->count());
        $tercero = $torneo->partidos()->where('llave', 'tercer_puesto')->firstOrFail();

        $this->resolver($tercero);

        $this->assertSame('finalizado', $torneo->refresh()->estado->value);
        $this->assertDatabaseHas('premios_entregados', ['torneo_id' => $torneo->id, 'puesto' => 3, 'equipo_id' => $tercero->equipo_a_id, 'monto_centavos' => 250_000]);
    }

    public function test_battle_royale_calcula_puntos_clasificacion_y_premios(): void
    {
        $torneo = $this->individualCompleto(['juego' => Juego::FreeFire, 'formato' => FormatoTorneo::BattleRoyale, 'partidas_battle_royale' => 1]);
        $this->iniciar($torneo);
        $equipos = $torneo->participantes()->pluck('equipo_id')->all();
        $partido = $torneo->partidos()->firstOrFail();

        $this->actingAs($this->organizador())->postJson("/api/v1/admin/partidos/{$partido->id}/battle-royale", ['resultados' => [
            ['equipo_id' => $equipos[0], 'posicion' => 1, 'bajas' => 0], ['equipo_id' => $equipos[1], 'posicion' => 2, 'bajas' => 5],
        ]])->assertOk();

        $this->assertSame($equipos[1], $torneo->refresh()->campeon_id);
        $this->assertDatabaseHas('resultados_battle_royale', ['equipo_id' => $equipos[1], 'puntos' => 14]);
        $this->assertDatabaseHas('premios_entregados', ['torneo_id' => $torneo->id, 'puesto' => 1, 'equipo_id' => $equipos[1]]);
    }

    public function test_el_premio_del_equipo_pertenece_al_inscriptor_original(): void
    {
        $torneo = $this->torneoAutomatico(['modalidad' => 'equipos', 'costo_inscripcion_centavos' => 0]);
        $equipos = Equipo::factory()->count(2)->completo()->create();
        foreach ($equipos as $equipo) {
            $this->actingAs($equipo->capitan)->postJson("/api/v1/panel/torneos/{$torneo->slug}/inscripciones", ['equipo_id' => $equipo->id])->assertCreated();
        }
        $this->iniciar($torneo);
        $final = $torneo->partidos()->firstOrFail();
        $original = $final->equipoA->capitan_id;
        $nuevo = $final->equipoA->miembros()->whereKeyNot($original)->firstOrFail();
        $final->equipoA->forceFill(['capitan_id' => $nuevo->id])->save();

        $this->resolver($final);

        $this->assertDatabaseHas('carteras', ['usuario_id' => $original, 'saldo_centavos' => 1_000_000]);
        $this->assertSame(0, app(GestorDeCarteras::class)->saldo($nuevo->id));
    }

    public function test_corregir_la_gran_final_para_crear_revancha_recupera_los_premios_anteriores(): void
    {
        $torneo = $this->individualCompleto(['formato' => FormatoTorneo::EliminacionDoble, 'cupo_maximo_equipos' => 4]);
        $this->iniciar($torneo);
        while ($partido = $torneo->partidos()->where('estado', 'por_jugar')->where('llave', '!=', 'gran_final')->orderBy('id')->first()) {
            $this->resolver($partido);
        }
        $final = $torneo->partidos()->where('llave', 'gran_final')->firstOrFail();
        $this->resolver($final);
        $this->assertSame(2, DB::table('premios_entregados')->count());

        $this->resolver($final->refresh(), 'b');

        $this->assertSame('en_curso', $torneo->refresh()->estado->value);
        $this->assertSame(0, DB::table('premios_entregados')->count());
        $this->assertSame(0, (int) DB::table('carteras')->sum('saldo_centavos'));
        $revancha = $torneo->partidos()->where('llave', 'gran_final')->where('ronda', 2)->firstOrFail();
        $this->resolver($revancha);
        $this->assertSame(1_500_000, (int) DB::table('carteras')->sum('saldo_centavos'));
    }

    public function test_grupos_y_suizo_terminan_con_clasificacion_y_premios_automaticos(): void
    {
        foreach ([
            ['formato' => FormatoTorneo::GruposYEliminacion, 'cupo_maximo_equipos' => 6, 'cantidad_de_grupos' => 2, 'clasifican_por_grupo' => 2],
            ['formato' => FormatoTorneo::Suizo, 'cupo_maximo_equipos' => 4, 'rondas_suizo' => 3],
        ] as $ajustes) {
            $torneo = $this->individualCompleto($ajustes);
            $this->iniciar($torneo);
            $contador = 0;
            while ($partido = $torneo->partidos()->where('estado', 'por_jugar')->orderBy('id')->first()) {
                $this->resolver($partido);
                $this->assertLessThan(30, ++$contador);
            }
            $this->assertSame('finalizado', $torneo->refresh()->estado->value);
            $this->assertSame(2, DB::table('premios_entregados')->where('torneo_id', $torneo->id)->count());
            $this->getJson("/api/v1/torneos/{$torneo->slug}")->assertOk()->assertJsonPath('data.clasificacion_final.0.equipo.id', $torneo->campeon_id);
        }
    }

    public function test_rechaza_grupos_imposibles_antes_de_publicar(): void
    {
        $this->actingAs($this->organizador())->postJson('/api/v1/admin/torneos', [
            'nombre' => 'Grupos imposibles', 'juego' => 'valorant', 'formato' => 'grupos_y_eliminacion',
            'cupo_maximo_equipos' => 4, 'cantidad_de_grupos' => 2, 'clasifican_por_grupo' => 2,
        ])->assertUnprocessable();
    }

    public function test_los_datos_de_exposicion_son_completos_y_no_duplican_premios_al_resembrar(): void
    {
        $this->seed(ExposicionSeeder::class);
        $this->assertSame(9, Torneo::count());
        $usuario = Usuario::where('correo', 'jugador@juegoszone.com')->firstOrFail();
        $this->assertSame(5_000_000, app(GestorDeCarteras::class)->saldo($usuario->id));
        $movimientos = DB::table('movimientos_cartera')->count();
        $this->seed(ExposicionSeeder::class);
        $this->assertSame(9, Torneo::count());
        $this->assertSame($movimientos, DB::table('movimientos_cartera')->count());
        $this->assertSame(5_000_000, app(GestorDeCarteras::class)->saldo($usuario->id));
        $this->assertDatabaseHas('torneos', ['nombre' => 'Booyah Showcase — Escuadras', 'estado' => 'en_curso']);
    }
}
