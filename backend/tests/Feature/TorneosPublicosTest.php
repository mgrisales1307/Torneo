<?php

namespace Tests\Feature;

use App\Enumeraciones\TipoEventoPartido;
use App\Models\Equipo;
use App\Models\EventoPartido;
use App\Models\Inscripcion;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\Torneo;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Lo que ve cualquier visitante sin cuenta.
 */
class TorneosPublicosTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_el_listado_publico_no_muestra_los_torneos_en_borrador(): void
    {
        $publicado = Torneo::factory()->create(['nombre' => 'Copa Visible']);
        Torneo::factory()->borrador()->create(['nombre' => 'Copa Escondida']);

        $respuesta = $this->getJson('/api/v1/torneos')->assertOk();

        $nombres = collect($respuesta->json('data'))->pluck('nombre');

        $this->assertTrue($nombres->contains($publicado->nombre));
        $this->assertFalse($nombres->contains('Copa Escondida'));
    }

    public function test_un_torneo_en_borrador_devuelve_403_al_publico_y_se_ve_desde_la_organizacion(): void
    {
        $torneo = Torneo::factory()->borrador()->create();

        $this->getJson("/api/v1/torneos/{$torneo->slug}")->assertForbidden();

        $this->actingAs(Usuario::factory()->empleado()->create())
            ->getJson("/api/v1/torneos/{$torneo->slug}")
            ->assertOk()
            ->assertJsonPath('data.nombre', $torneo->nombre);
    }

    public function test_la_tabla_de_posiciones_ordena_por_puntos_diferencia_y_goles(): void
    {
        $torneo = Torneo::factory()->enCurso()->create();
        [$leon, $tigre, $puma] = $this->equiposInscritos($torneo, 3);

        // León gana 3-0, Tigre gana 1-0: los dos suman 3, pero León tiene mejor
        // diferencia de gol.
        Partido::factory()->delTorneo($torneo)->entre($leon, $puma)->finalizado(3, 0)->create();
        Partido::factory()->delTorneo($torneo)->entre($tigre, $puma)->finalizado(1, 0)->enJornada(2)->create();

        $tabla = $this->getJson("/api/v1/torneos/{$torneo->slug}/tabla")->assertOk()->json('data');

        $this->assertSame($leon->nombre, $tabla[0]['equipo']['nombre']);
        $this->assertSame(3, $tabla[0]['puntos']);
        $this->assertSame(3, $tabla[0]['diferencia_de_gol']);

        $this->assertSame($tigre->nombre, $tabla[1]['equipo']['nombre']);
        $this->assertSame(3, $tabla[1]['puntos']);

        $this->assertSame($puma->nombre, $tabla[2]['equipo']['nombre']);
        $this->assertSame(0, $tabla[2]['puntos']);
        $this->assertSame(2, $tabla[2]['partidos_jugados']);
    }

    public function test_la_tabla_no_cuenta_los_partidos_que_no_han_terminado(): void
    {
        $torneo = Torneo::factory()->enCurso()->create();
        [$local, $visitante] = $this->equiposInscritos($torneo, 2);

        Partido::factory()->delTorneo($torneo)->entre($local, $visitante)->create();

        $tabla = $this->getJson("/api/v1/torneos/{$torneo->slug}/tabla")->assertOk()->json('data');

        $this->assertSame(0, $tabla[0]['partidos_jugados']);
        $this->assertSame(0, $tabla[0]['puntos']);
    }

    public function test_el_calendario_se_puede_filtrar_por_jornada(): void
    {
        $torneo = Torneo::factory()->enCurso()->create();
        [$local, $visitante] = $this->equiposInscritos($torneo, 2);

        Partido::factory()->delTorneo($torneo)->entre($local, $visitante)->enJornada(1)->create();
        Partido::factory()->delTorneo($torneo)->entre($visitante, $local)->enJornada(2)->create();

        $this->getJson("/api/v1/torneos/{$torneo->slug}/partidos")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.jornadas', 2);

        $this->getJson("/api/v1/torneos/{$torneo->slug}/partidos?jornada=2")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.jornada', 2);
    }

    public function test_la_tabla_de_goleadores_cuenta_goles_y_penales_pero_no_autogoles(): void
    {
        $torneo = Torneo::factory()->enCurso()->create();
        [$local, $visitante] = $this->equiposInscritos($torneo, 2);

        $delantero = Jugador::factory()->delEquipo($local)->conDorsal(9)->create();
        $defensa = Jugador::factory()->delEquipo($visitante)->conDorsal(4)->create();

        $partido = Partido::factory()->delTorneo($torneo)->entre($local, $visitante)->finalizado(3, 0)->create();

        EventoPartido::factory()->deJugador($delantero)->for($partido)->create();
        EventoPartido::factory()->deJugador($delantero)->for($partido)->tipo(TipoEventoPartido::GolDePenal)->create();
        EventoPartido::factory()->deJugador($defensa)->for($partido)->tipo(TipoEventoPartido::Autogol)->create();

        $goleadores = $this->getJson("/api/v1/torneos/{$torneo->slug}/goleadores")->assertOk()->json('data');

        $this->assertCount(1, $goleadores);
        $this->assertSame($delantero->nombreCompleto(), $goleadores[0]['jugador']['nombre_completo']);
        $this->assertSame(2, $goleadores[0]['goles']);
        $this->assertSame(1, $goleadores[0]['goles_de_penal']);
    }

    public function test_la_ficha_publica_de_un_equipo_no_expone_datos_personales_de_los_jugadores(): void
    {
        $equipo = Equipo::factory()->create();
        Jugador::factory()->delEquipo($equipo)->conDorsal(10)->create([
            'documento_identidad' => '1023456789',
            'telefono' => '300 111 2233',
            'correo' => 'jugador@equipo.com',
        ]);

        $respuesta = $this->getJson("/api/v1/equipos/{$equipo->slug}")->assertOk();

        $respuesta->assertJsonPath('data.jugadores.0.numero_camiseta', 10);

        $jugador = $respuesta->json('data.jugadores.0');
        $this->assertArrayNotHasKey('documento_identidad', $jugador);
        $this->assertArrayNotHasKey('telefono', $jugador);
        $this->assertArrayNotHasKey('correo', $jugador);
    }

    public function test_la_portada_trae_torneos_proximos_partidos_y_resultados(): void
    {
        $torneo = Torneo::factory()->enCurso()->create();
        [$local, $visitante] = $this->equiposInscritos($torneo, 2);

        Partido::factory()->delTorneo($torneo)->entre($local, $visitante)->create([
            'programado_para' => now()->addDays(3),
        ]);
        Partido::factory()->delTorneo($torneo)->entre($visitante, $local)->finalizado(2, 1)->enJornada(2)->create();

        $this->getJson('/api/v1/inicio')
            ->assertOk()
            ->assertJsonCount(1, 'torneos')
            ->assertJsonCount(1, 'proximos_partidos')
            ->assertJsonCount(1, 'ultimos_resultados')
            ->assertJsonPath('cifras.equipos', 2)
            ->assertJsonPath('cifras.partidos_jugados', 1);
    }

    /**
     * @return array<int, Equipo>
     */
    private function equiposInscritos(Torneo $torneo, int $cantidad): array
    {
        $equipos = [];

        foreach (range(1, $cantidad) as $numero) {
            $equipo = Equipo::factory()->create();

            Inscripcion::factory()->aprobada()->create([
                'torneo_id' => $torneo->getKey(),
                'equipo_id' => $equipo->getKey(),
            ]);

            $equipos[] = $equipo;
        }

        return $equipos;
    }
}
