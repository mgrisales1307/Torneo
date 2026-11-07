<?php

namespace Tests\Feature;

use App\Models\Equipo;
use App\Models\Inscripcion;
use App\Models\Torneo;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Códigos de invitación, baja de equipos y validación de sus datos.
 */
class CodigosYBajasDeEquipoTest extends TestCase
{
    use LazilyRefreshDatabase;

    /* ---------------------------------------------------------------- Código */

    public function test_el_capitan_genera_un_codigo_nuevo_y_el_anterior_deja_de_servir(): void
    {
        $equipo = Equipo::factory()->create();
        $anterior = $equipo->codigo_invitacion;

        $respuesta = $this->actingAs($equipo->capitan)
            ->postJson("/api/v1/panel/equipos/{$equipo->slug}/codigo")
            ->assertOk();

        $nuevo = $equipo->fresh()->codigo_invitacion;

        $this->assertNotSame($anterior, $nuevo);
        $this->assertSame($nuevo, $respuesta->json('data.codigo_invitacion'));

        $this->actingAs(Usuario::factory()->create())
            ->postJson('/api/v1/panel/unirse', ['codigo' => $anterior])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('codigo');

        $this->postJson('/api/v1/panel/unirse', ['codigo' => $nuevo])->assertOk();
    }

    public function test_solo_el_capitan_cambia_el_codigo(): void
    {
        $equipo = Equipo::factory()->create();
        $miembro = Usuario::factory()->create();
        $equipo->miembros()->attach($miembro->id, ['es_suplente' => false]);
        $codigo = $equipo->codigo_invitacion;

        $this->actingAs($miembro)->postJson("/api/v1/panel/equipos/{$equipo->slug}/codigo")->assertForbidden();
        $this->actingAs(Usuario::factory()->create())->postJson("/api/v1/panel/equipos/{$equipo->slug}/codigo")->assertForbidden();

        $this->assertSame($codigo, $equipo->fresh()->codigo_invitacion);
    }

    public function test_sin_sesion_no_se_cambia_el_codigo(): void
    {
        $equipo = Equipo::factory()->create();

        $this->postJson("/api/v1/panel/equipos/{$equipo->slug}/codigo")->assertUnauthorized();
    }

    public function test_unirse_exige_un_codigo(): void
    {
        $this->actingAs(Usuario::factory()->create())
            ->postJson('/api/v1/panel/unirse', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('codigo');
    }

    public function test_probar_codigos_al_azar_se_frena_con_un_limite_de_intentos(): void
    {
        $this->actingAs(Usuario::factory()->create());

        for ($intento = 1; $intento <= 10; $intento++) {
            $this->postJson('/api/v1/panel/unirse', ['codigo' => 'ZZZZZZZZ'])->assertUnprocessable();
        }

        $this->postJson('/api/v1/panel/unirse', ['codigo' => 'ZZZZZZZZ'])->assertStatus(429);
    }

    /* -------------------------------------------------------------------- Baja */

    public function test_el_capitan_elimina_su_equipo(): void
    {
        $equipo = Equipo::factory()->create();

        $this->actingAs($equipo->capitan)
            ->deleteJson("/api/v1/panel/equipos/{$equipo->slug}")
            ->assertOk();

        $this->assertNull(Equipo::query()->find($equipo->id));
        $this->getJson('/api/v1/panel/equipos')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_un_miembro_que_no_es_capitan_no_elimina_el_equipo(): void
    {
        $equipo = Equipo::factory()->create();
        $miembro = Usuario::factory()->create();
        $equipo->miembros()->attach($miembro->id, ['es_suplente' => false]);

        $this->actingAs($miembro)->deleteJson("/api/v1/panel/equipos/{$equipo->slug}")->assertForbidden();
        $this->actingAs(Usuario::factory()->empleado()->create())->deleteJson("/api/v1/panel/equipos/{$equipo->slug}")->assertForbidden();

        $this->assertNotNull(Equipo::query()->find($equipo->id));
    }

    public function test_el_administrador_si_puede_eliminar_un_equipo_ajeno(): void
    {
        $equipo = Equipo::factory()->create();

        $this->actingAs(Usuario::factory()->administrador()->create())
            ->deleteJson("/api/v1/panel/equipos/{$equipo->slug}")
            ->assertOk();

        $this->assertNull(Equipo::query()->find($equipo->id));
    }

    public function test_no_se_elimina_un_equipo_con_inscripcion_pagada_en_un_torneo_activo(): void
    {
        $equipo = Equipo::factory()->completo()->create();
        Inscripcion::factory()->aprobada()->create([
            'torneo_id' => Torneo::factory()->create()->id,
            'equipo_id' => $equipo->id,
        ]);

        $this->actingAs($equipo->capitan)
            ->deleteJson("/api/v1/panel/equipos/{$equipo->slug}")
            ->assertUnprocessable();

        $this->assertNotNull(Equipo::query()->find($equipo->id));
    }

    public function test_si_el_torneo_ya_termino_el_equipo_se_puede_eliminar(): void
    {
        $equipo = Equipo::factory()->completo()->create();
        Inscripcion::factory()->aprobada()->create([
            'torneo_id' => Torneo::factory()->finalizado()->create()->id,
            'equipo_id' => $equipo->id,
        ]);

        $this->actingAs($equipo->capitan)
            ->deleteJson("/api/v1/panel/equipos/{$equipo->slug}")
            ->assertOk();

        $this->assertNull(Equipo::query()->find($equipo->id));
    }

    /* ---------------------------------------------------------- Validaciones */

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function equiposInvalidos(): array
    {
        return [
            'nombre muy corto' => [['nombre' => 'ab'], 'nombre'],
            'sin nombre' => [['nombre' => ''], 'nombre'],
            'sigla de una letra' => [['abreviatura' => 'A'], 'abreviatura'],
            'sigla muy larga' => [['abreviatura' => 'ABCDEF'], 'abreviatura'],
            'sigla con simbolos' => [['abreviatura' => 'N-Z'], 'abreviatura'],
            'color en palabras' => [['color' => 'rojo'], 'color'],
            'color sin almohadilla' => [['color' => 'FF4655'], 'color'],
            'juego inexistente' => [['juego' => 'fortnite'], 'juego'],
            'sin juego' => [['juego' => null], 'juego'],
        ];
    }

    /**
     * @param  array<string, mixed>  $sobre
     */
    #[DataProvider('equiposInvalidos')]
    public function test_los_datos_invalidos_del_equipo_se_rechazan(array $sobre, string $campo): void
    {
        $datos = ['juego' => 'valorant', 'nombre' => 'Cóndores', 'abreviatura' => 'CON', 'color' => '#FF4655', ...$sobre];

        $this->actingAs(Usuario::factory()->create())
            ->postJson('/api/v1/panel/equipos', $datos)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($campo);

        $this->assertSame(0, Equipo::query()->count());
    }

    public function test_el_nombre_y_la_sigla_se_guardan_sin_espacios_sobrantes(): void
    {
        $usuario = Usuario::factory()->create();

        $this->actingAs($usuario)
            ->postJson('/api/v1/panel/equipos', ['juego' => 'valorant', 'nombre' => '  Cóndores Andinos  ', 'abreviatura' => ' CON ', 'color' => '#FF4655'])
            ->assertCreated()
            ->assertJsonPath('data.nombre', 'Cóndores Andinos');

        $this->assertSame('Cóndores Andinos', Equipo::query()->firstOrFail()->nombre);
    }
}
