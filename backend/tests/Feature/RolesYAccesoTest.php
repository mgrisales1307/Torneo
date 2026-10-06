<?php

namespace Tests\Feature;

use App\Enumeraciones\RolUsuario;
use App\Models\Torneo;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * La matriz de acceso completa: qué puede hacer cada rol y, sobre todo, qué no.
 */
class RolesYAccesoTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{string, int}>
     */
    public static function rolesDelPanelDeOrganizacion(): array
    {
        return [
            'administrador entra' => [RolUsuario::Administrador->value, 200],
            'empleado entra' => [RolUsuario::Empleado->value, 200],
            'jugador no entra' => [RolUsuario::Jugador->value, 403],
        ];
    }

    #[DataProvider('rolesDelPanelDeOrganizacion')]
    public function test_el_listado_de_usuarios_es_solo_para_la_organizacion(string $rol, int $esperado): void
    {
        $usuario = Usuario::factory()->create(['rol' => $rol]);

        $this->actingAs($usuario)->getJson('/api/v1/admin/usuarios')->assertStatus($esperado);
    }

    #[DataProvider('rolesDelPanelDeOrganizacion')]
    public function test_el_resumen_de_la_organizacion_es_solo_para_la_organizacion(string $rol, int $esperado): void
    {
        $usuario = Usuario::factory()->create(['rol' => $rol]);

        $this->actingAs($usuario)->getJson('/api/v1/admin/resumen')->assertStatus($esperado);
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function rolesDelPanelDeEquipos(): array
    {
        return [
            'jugador entra' => [RolUsuario::Jugador->value, 200],
            'administrador entra' => [RolUsuario::Administrador->value, 200],
            'empleado entra' => [RolUsuario::Empleado->value, 200],
        ];
    }

    #[DataProvider('rolesDelPanelDeEquipos')]
    public function test_el_panel_de_equipos_es_para_cualquier_cuenta(string $rol, int $esperado): void
    {
        $usuario = Usuario::factory()->create(['rol' => $rol]);

        $this->actingAs($usuario)->getJson('/api/v1/panel/equipos')->assertStatus($esperado);
    }

    public function test_sin_sesion_los_paneles_devuelven_401(): void
    {
        $this->getJson('/api/v1/panel/equipos')->assertUnauthorized();
        $this->getJson('/api/v1/admin/usuarios')->assertUnauthorized();
        $this->getJson('/api/v1/admin/resumen')->assertUnauthorized();
    }

    public function test_sin_sesion_las_rutas_publicas_responden(): void
    {
        Torneo::factory()->create();

        $this->getJson('/api/v1/inicio')->assertOk();
        $this->getJson('/api/v1/juegos')->assertOk()->assertJsonCount(5, 'data');
        $this->getJson('/api/v1/juegos/valorant')->assertOk();
        $this->getJson('/api/v1/juegos/minecraft')->assertNotFound();
        $this->getJson('/api/v1/torneos')->assertOk();
    }

    public function test_solo_la_administracion_elimina_torneos(): void
    {
        $torneo = Torneo::factory()->create();

        $this->actingAs(Usuario::factory()->empleado()->create())
            ->deleteJson("/api/v1/admin/torneos/{$torneo->slug}")
            ->assertForbidden();

        $this->actingAs(Usuario::factory()->administrador()->create())
            ->deleteJson("/api/v1/admin/torneos/{$torneo->slug}")
            ->assertOk();

        $this->assertSoftDeleted($torneo);
    }

    public function test_el_empleado_crea_y_publica_torneos(): void
    {
        $empleado = Usuario::factory()->empleado()->create();

        $respuesta = $this->actingAs($empleado)->postJson('/api/v1/admin/torneos', [
            'juego' => 'valorant',
            'nombre' => 'Copa de la Universidad',
            'formato' => 'eliminacion_doble',
            'cupo_maximo_equipos' => 8,
            'mejor_de' => 3,
            'costo_inscripcion_centavos' => 10_000_000,
            'premios' => [50_000_000, 20_000_000, 0],
        ])->assertCreated()
            ->assertJsonPath('data.estado.valor', 'borrador')
            ->assertJsonPath('data.premios.total_formateado', '$ 700.000');

        $slug = $respuesta->json('data.slug');

        $this->actingAs($empleado)
            ->postJson("/api/v1/admin/torneos/$slug/estado", ['estado' => 'inscripciones_abiertas'])
            ->assertOk()
            ->assertJsonPath('data.estado.valor', 'inscripciones_abiertas');

        // «En curso» no se pone a mano: se llega iniciando el torneo.
        $this->actingAs($empleado)
            ->postJson("/api/v1/admin/torneos/$slug/estado", ['estado' => 'en_curso'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('estado');
    }

    public function test_el_formato_tiene_que_ser_del_tipo_del_juego(): void
    {
        $administrador = Usuario::factory()->administrador()->create();

        $this->actingAs($administrador)->postJson('/api/v1/admin/torneos', [
            'juego' => 'pubg',
            'nombre' => 'Copa Imposible',
            'formato' => 'eliminacion_simple',
            'cupo_maximo_equipos' => 8,
        ])->assertUnprocessable()->assertJsonValidationErrors('formato');

        $this->actingAs($administrador)->postJson('/api/v1/admin/torneos', [
            'juego' => 'freefire',
            'nombre' => 'Sala Llena',
            'formato' => 'battle_royale',
            'cupo_maximo_equipos' => 20,
        ])->assertUnprocessable()->assertJsonValidationErrors('cupo_maximo_equipos');

        $this->actingAs($administrador)->postJson('/api/v1/admin/torneos', [
            'juego' => 'lol',
            'nombre' => 'Grupos sin Grupos',
            'formato' => 'grupos_y_eliminacion',
            'cupo_maximo_equipos' => 8,
        ])->assertUnprocessable()->assertJsonValidationErrors(['cantidad_de_grupos', 'clasifican_por_grupo']);
    }

    public function test_las_respuestas_traen_las_cabeceras_de_seguridad(): void
    {
        $this->getJson('/api/v1/torneos')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }
}
