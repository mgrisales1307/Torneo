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
            'dueño no entra' => [RolUsuario::Dueno->value, 403],
            'espectador no entra' => [RolUsuario::Espectador->value, 403],
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
            'dueño entra' => [RolUsuario::Dueno->value, 200],
            'administrador entra' => [RolUsuario::Administrador->value, 200],
            'empleado entra' => [RolUsuario::Empleado->value, 200],
            'espectador no entra' => [RolUsuario::Espectador->value, 403],
        ];
    }

    #[DataProvider('rolesDelPanelDeEquipos')]
    public function test_el_panel_de_equipos_no_es_para_espectadores(string $rol, int $esperado): void
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

    public function test_el_empleado_si_crea_y_edita_torneos(): void
    {
        $empleado = Usuario::factory()->empleado()->create();

        $respuesta = $this->actingAs($empleado)->postJson('/api/v1/admin/torneos', [
            'nombre' => 'Copa de la Universidad',
            'formato' => 'liga_ida',
            'cupo_maximo_equipos' => 8,
            'minimo_jugadores_por_equipo' => 7,
            'maximo_jugadores_por_equipo' => 18,
            'costo_inscripcion_centavos' => 10_000_000,
        ])->assertCreated();

        $slug = $respuesta->json('data.slug');

        $this->actingAs($empleado)
            ->patchJson("/api/v1/admin/torneos/$slug", ['estado' => 'inscripciones_abiertas'])
            ->assertOk()
            ->assertJsonPath('data.estado.valor', 'inscripciones_abiertas');
    }

    public function test_el_maximo_de_jugadores_no_puede_ser_menor_que_el_minimo(): void
    {
        $this->actingAs(Usuario::factory()->administrador()->create())->postJson('/api/v1/admin/torneos', [
            'nombre' => 'Copa Inconsistente',
            'formato' => 'liga_ida',
            'cupo_maximo_equipos' => 8,
            'minimo_jugadores_por_equipo' => 20,
            'maximo_jugadores_por_equipo' => 10,
        ])->assertUnprocessable()->assertJsonValidationErrors('maximo_jugadores_por_equipo');
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
