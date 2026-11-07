<?php

namespace Tests\Feature;

use App\Enumeraciones\Juego;
use App\Models\Equipo;
use App\Models\Inscripcion;
use App\Models\Torneo;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Lo que ve cualquier visitante sin cuenta: listados, fichas y perfiles. Lo
 * importante aquí es lo que NO sale: borradores, códigos y datos personales.
 */
class PublicoTest extends TestCase
{
    use LazilyRefreshDatabase;

    /* ---------------------------------------------------------------- Torneos */

    public function test_el_listado_publico_oculta_los_borradores_y_pone_primero_los_que_estan_en_curso(): void
    {
        Torneo::factory()->create(['nombre' => 'Copa Alfa']);
        Torneo::factory()->enCurso()->deJuego(Juego::LeagueOfLegends)->create(['nombre' => 'Copa Beta']);
        Torneo::factory()->borrador()->create(['nombre' => 'Copa Oculta']);

        $respuesta = $this->getJson('/api/v1/torneos')->assertOk()->assertJsonCount(2, 'data');

        $this->assertSame(['Copa Beta', 'Copa Alfa'], $respuesta->json('data.*.nombre'));
        $this->assertStringNotContainsString('Copa Oculta', $respuesta->getContent());
    }

    public function test_el_listado_de_torneos_se_filtra_por_juego_estado_y_texto(): void
    {
        Torneo::factory()->create(['nombre' => 'Copa Alfa']);
        Torneo::factory()->enCurso()->deJuego(Juego::LeagueOfLegends)->create(['nombre' => 'Copa Beta']);

        $this->getJson('/api/v1/torneos?juego=lol')->assertJsonCount(1, 'data')->assertJsonPath('data.0.nombre', 'Copa Beta');
        $this->getJson('/api/v1/torneos?estado=inscripciones_abiertas')->assertJsonCount(1, 'data')->assertJsonPath('data.0.nombre', 'Copa Alfa');
        $this->getJson('/api/v1/torneos?buscar=Alf')->assertJsonCount(1, 'data')->assertJsonPath('data.0.nombre', 'Copa Alfa');
        $this->getJson('/api/v1/torneos?buscar=inexistente')->assertJsonCount(0, 'data');
    }

    public function test_el_detalle_de_un_torneo_abierto_no_trae_clasificacion_final(): void
    {
        $torneo = Torneo::factory()->create();

        $this->getJson("/api/v1/torneos/{$torneo->slug}")
            ->assertOk()
            ->assertJsonPath('data.slug', $torneo->slug)
            ->assertJsonPath('data.clasificacion_final', []);
    }

    public function test_un_torneo_inexistente_devuelve_404(): void
    {
        $this->getJson('/api/v1/torneos/no-existe')->assertNotFound();
        $this->getJson('/api/v1/torneos/no-existe/equipos')->assertNotFound();
        $this->getJson('/api/v1/torneos/no-existe/cuadro')->assertNotFound();
    }

    public function test_los_equipos_de_un_torneo_son_solo_los_aprobados(): void
    {
        $torneo = Torneo::factory()->create();
        $aprobado = Equipo::factory()->completo()->create(['nombre' => 'Equipo Aprobado']);
        $pendiente = Equipo::factory()->completo()->create(['nombre' => 'Equipo Pendiente']);

        Inscripcion::factory()->aprobada()->create(['torneo_id' => $torneo->id, 'equipo_id' => $aprobado->id]);
        Inscripcion::factory()->create(['torneo_id' => $torneo->id, 'equipo_id' => $pendiente->id]);

        $respuesta = $this->getJson("/api/v1/torneos/{$torneo->slug}/equipos")->assertOk()->assertJsonCount(1, 'data');

        $this->assertSame('Equipo Aprobado', $respuesta->json('data.0.nombre'));
    }

    /* ---------------------------------------------------------------- Juegos */

    public function test_la_pagina_de_un_juego_trae_su_ficha_sus_torneos_y_su_ranking(): void
    {
        Torneo::factory()->create();
        Torneo::factory()->deJuego(Juego::Dota2)->create();

        $this->getJson('/api/v1/juegos/valorant')
            ->assertOk()
            ->assertJsonPath('juego.valor', 'valorant')
            ->assertJsonCount(1, 'torneos')
            ->assertJsonStructure(['juego', 'torneos', 'ranking']);
    }

    public function test_un_juego_que_no_existe_devuelve_404(): void
    {
        $this->getJson('/api/v1/juegos/fortnite')->assertNotFound();
    }

    /* ---------------------------------------------------------------- Equipos */

    public function test_la_ficha_publica_del_equipo_no_revela_el_codigo_de_invitacion(): void
    {
        $equipo = Equipo::factory()->completo()->create();

        $respuesta = $this->getJson("/api/v1/equipos/{$equipo->slug}")
            ->assertOk()
            ->assertJsonPath('data.nombre', $equipo->nombre)
            ->assertJsonPath('titulos', 0)
            ->assertJsonMissingPath('data.codigo_invitacion');

        $this->assertStringNotContainsString($equipo->codigo_invitacion, $respuesta->getContent());
    }

    public function test_un_equipo_inexistente_o_eliminado_devuelve_404(): void
    {
        $this->getJson('/api/v1/equipos/no-existe')->assertNotFound();

        $equipo = Equipo::factory()->create();
        $slug = $equipo->slug;
        $equipo->delete();

        $this->getJson("/api/v1/equipos/{$slug}")->assertNotFound();
    }

    /* --------------------------------------------------------------- Jugadores */

    public function test_el_perfil_publico_muestra_el_apodo_pero_no_datos_personales(): void
    {
        $usuario = Usuario::factory()->create([
            'apodo' => 'CondorAndino',
            'nombre' => 'Laura Gómez Restrepo',
            'correo' => 'laura.privada@ejemplo.test',
            'telefono' => '300 111 2222',
        ]);

        $respuesta = $this->getJson('/api/v1/jugadores/CondorAndino')
            ->assertOk()
            ->assertJsonPath('data.apodo', 'CondorAndino')
            ->assertJsonPath('cifras.partidos', 0)
            ->assertJsonPath('cifras.porcentaje_de_victorias', null);

        $contenido = $respuesta->getContent();

        $this->assertStringNotContainsString('laura.privada@ejemplo.test', $contenido);
        $this->assertStringNotContainsString('Laura Gómez', $contenido);
        $this->assertStringNotContainsString('Laura G\u00f3mez', $contenido);
        $this->assertStringNotContainsString('300 111 2222', $contenido);
        $this->assertNotNull($usuario->id);
    }

    public function test_un_jugador_inexistente_devuelve_404(): void
    {
        $this->getJson('/api/v1/jugadores/nadie')->assertNotFound();
    }

    /* ---------------------------------------------------------------- Partidos */

    public function test_un_partido_inexistente_devuelve_404(): void
    {
        $this->getJson('/api/v1/partidos/999999')->assertNotFound();
    }

    public function test_las_rutas_publicas_son_solo_de_lectura(): void
    {
        $torneo = Torneo::factory()->create();

        $this->postJson('/api/v1/torneos', [])->assertStatus(405);
        $this->deleteJson("/api/v1/torneos/{$torneo->slug}")->assertStatus(405);
        $this->patchJson("/api/v1/torneos/{$torneo->slug}", ['nombre' => 'Hackeado'])->assertStatus(405);
    }
}
