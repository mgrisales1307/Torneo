<?php

namespace Tests\Feature;

use App\Enumeraciones\EstadoInscripcion;
use App\Enumeraciones\EstadoTorneo;
use App\Models\Equipo;
use App\Models\Inscripcion;
use App\Models\Torneo;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Alta, edición, cambios de estado y baja de torneos por parte de la organización.
 */
class TorneosAdminTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @param  array<string, mixed>  $sobre
     * @return array<string, mixed>
     */
    private function datosDeTorneo(array $sobre = []): array
    {
        return [
            'juego' => 'valorant',
            'nombre' => 'Copa Andina',
            'formato' => 'eliminacion_simple',
            'cupo_maximo_equipos' => 8,
            'costo_inscripcion_centavos' => 5_000_000,
            'moneda' => 'COP',
            ...$sobre,
        ];
    }

    /* ------------------------------------------------------------------ Alta */

    public function test_el_empleado_crea_un_torneo_y_queda_publicado(): void
    {
        $empleado = Usuario::factory()->empleado()->create();

        $this->actingAs($empleado)
            ->postJson('/api/v1/admin/torneos', $this->datosDeTorneo())
            ->assertCreated()
            ->assertJsonPath('data.nombre', 'Copa Andina')
            ->assertJsonPath('data.slug', 'copa-andina')
            ->assertJsonPath('data.estado.valor', EstadoTorneo::InscripcionesAbiertas->value);

        $torneo = Torneo::query()->where('slug', 'copa-andina')->firstOrFail();

        $this->assertSame($empleado->id, $torneo->creado_por_id);
    }

    public function test_dos_torneos_con_el_mismo_nombre_reciben_slugs_distintos(): void
    {
        $administrador = Usuario::factory()->administrador()->create();

        $this->actingAs($administrador)->postJson('/api/v1/admin/torneos', $this->datosDeTorneo())->assertCreated();
        $this->postJson('/api/v1/admin/torneos', $this->datosDeTorneo())
            ->assertCreated()
            ->assertJsonPath('data.slug', 'copa-andina-2');
    }

    public function test_un_jugador_no_crea_torneos(): void
    {
        $this->actingAs(Usuario::factory()->create())
            ->postJson('/api/v1/admin/torneos', $this->datosDeTorneo())
            ->assertForbidden();

        $this->assertSame(0, Torneo::query()->count());
    }

    public function test_sin_sesion_no_se_crean_torneos(): void
    {
        $this->postJson('/api/v1/admin/torneos', $this->datosDeTorneo())->assertUnauthorized();
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function torneosInvalidos(): array
    {
        return [
            'nombre muy corto' => [['nombre' => 'abc'], 'nombre'],
            'sin nombre' => [['nombre' => null], 'nombre'],
            'juego inexistente' => [['juego' => 'fortnite'], 'juego'],
            'battle royale en un juego de duelos' => [['formato' => 'battle_royale'], 'formato'],
            'duelos en un juego de battle royale' => [['juego' => 'pubg', 'formato' => 'eliminacion_simple'], 'formato'],
            'cupo menor al minimo del formato' => [['formato' => 'suizo', 'cupo_maximo_equipos' => 2], 'cupo_maximo_equipos'],
            'cupo mayor al de la sala' => [['juego' => 'pubg', 'formato' => 'battle_royale', 'cupo_maximo_equipos' => 17], 'cupo_maximo_equipos'],
            'cupo de uno' => [['cupo_maximo_equipos' => 1], 'cupo_maximo_equipos'],
            'mejor de cuatro' => [['mejor_de' => 4], 'mejor_de'],
            'costo negativo' => [['costo_inscripcion_centavos' => -1], 'costo_inscripcion_centavos'],
            'costo con decimales' => [['costo_inscripcion_centavos' => 10.5], 'costo_inscripcion_centavos'],
            'mas premios que participantes' => [['cupo_maximo_equipos' => 2, 'premios' => [300, 200, 100]], 'premios'],
            'mas de tres premios' => [['premios' => [4, 3, 2, 1]], 'premios'],
            'moneda distinta de COP' => [['moneda' => 'USD'], 'moneda'],
            'cierre antes de la apertura' => [['inscripciones_abren_en' => '2030-05-10 10:00:00', 'inscripciones_cierran_en' => '2030-05-01 10:00:00'], 'inscripciones_cierran_en'],
            'inicio antes del cierre' => [['inscripciones_abren_en' => '2030-04-01 10:00:00', 'inscripciones_cierran_en' => '2030-05-10 10:00:00', 'inicia_en' => '2030-05-01 10:00:00'], 'inicia_en'],
            'grupos sin configurar' => [['formato' => 'grupos_y_eliminacion', 'cupo_maximo_equipos' => 8], 'cantidad_de_grupos'],
        ];
    }

    /**
     * @param  array<string, mixed>  $sobre
     */
    #[DataProvider('torneosInvalidos')]
    public function test_los_datos_invalidos_se_rechazan_campo_por_campo(array $sobre, string $campo): void
    {
        $this->actingAs(Usuario::factory()->empleado()->create())
            ->postJson('/api/v1/admin/torneos', $this->datosDeTorneo($sobre))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($campo);

        $this->assertSame(0, Torneo::query()->count());
    }

    /* --------------------------------------------------------------- Lectura */

    public function test_el_listado_de_la_organizacion_incluye_borradores_y_filtra_por_estado(): void
    {
        Torneo::factory()->borrador()->create(['nombre' => 'Copa Borrador']);
        Torneo::factory()->create(['nombre' => 'Copa Abierta']);
        Torneo::factory()->finalizado()->create(['nombre' => 'Copa Vieja']);

        $this->actingAs(Usuario::factory()->empleado()->create())
            ->getJson('/api/v1/admin/torneos')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $this->getJson('/api/v1/admin/torneos?estado=borrador')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nombre', 'Copa Borrador');
    }

    public function test_el_detalle_trae_las_transiciones_permitidas(): void
    {
        $torneo = Torneo::factory()->borrador()->create();

        $this->actingAs(Usuario::factory()->empleado()->create())
            ->getJson("/api/v1/admin/torneos/{$torneo->slug}")
            ->assertOk()
            ->assertJsonPath('transiciones', ['inscripciones_abiertas', 'cancelado']);
    }

    public function test_un_jugador_no_ve_el_listado_de_la_organizacion(): void
    {
        $this->actingAs(Usuario::factory()->create())->getJson('/api/v1/admin/torneos')->assertForbidden();
    }

    /* ------------------------------------------------------------- Edición */

    public function test_la_organizacion_edita_el_nombre_y_las_reglas(): void
    {
        $torneo = Torneo::factory()->create();

        $this->actingAs(Usuario::factory()->empleado()->create())
            ->patchJson("/api/v1/admin/torneos/{$torneo->slug}", ['nombre' => 'Copa Renovada', 'reglas' => 'Reglas nuevas.'])
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Copa Renovada')
            ->assertJsonPath('data.reglas', 'Reglas nuevas.');

        $this->assertSame('Copa Renovada', $torneo->fresh()->nombre);
    }

    public function test_un_jugador_no_edita_torneos(): void
    {
        $torneo = Torneo::factory()->create();

        $this->actingAs(Usuario::factory()->create())
            ->patchJson("/api/v1/admin/torneos/{$torneo->slug}", ['nombre' => 'Copa Hackeada'])
            ->assertForbidden();

        $this->assertNotSame('Copa Hackeada', $torneo->fresh()->nombre);
    }

    public function test_con_el_torneo_en_curso_no_se_cambian_los_ajustes_estructurales(): void
    {
        $torneo = Torneo::factory()->enCurso()->create(['inscripciones_cierran_en' => now()->subDays(2)]);

        $this->actingAs(Usuario::factory()->empleado()->create())
            ->patchJson("/api/v1/admin/torneos/{$torneo->slug}", ['cupo_maximo_equipos' => 16])
            ->assertUnprocessable();

        $this->assertSame(8, $torneo->fresh()->cupo_maximo_equipos);

        $this->patchJson("/api/v1/admin/torneos/{$torneo->slug}", ['reglas' => 'Se puede cambiar el texto.'])
            ->assertOk();
    }

    /* -------------------------------------------------------------- Estados */

    public function test_el_torneo_recorre_borrador_abierto_y_cerrado(): void
    {
        $torneo = Torneo::factory()->borrador()->create();

        $this->actingAs(Usuario::factory()->empleado()->create())
            ->postJson("/api/v1/admin/torneos/{$torneo->slug}/estado", ['estado' => 'inscripciones_abiertas'])
            ->assertOk()
            ->assertJsonPath('data.estado.valor', 'inscripciones_abiertas');

        $this->postJson("/api/v1/admin/torneos/{$torneo->slug}/estado", ['estado' => 'inscripciones_cerradas'])
            ->assertOk()
            ->assertJsonPath('data.estado.valor', 'inscripciones_cerradas');

        $this->assertSame(EstadoTorneo::InscripcionesCerradas, $torneo->fresh()->estado);
    }

    public function test_no_se_puede_saltar_de_estado_a_mano(): void
    {
        $torneo = Torneo::factory()->inscripcionesCerradas()->create();

        $this->actingAs(Usuario::factory()->empleado()->create())
            ->postJson("/api/v1/admin/torneos/{$torneo->slug}/estado", ['estado' => 'en_curso'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('estado');

        $this->postJson("/api/v1/admin/torneos/{$torneo->slug}/estado", ['estado' => 'finalizado'])
            ->assertUnprocessable();

        $this->assertSame(EstadoTorneo::InscripcionesCerradas, $torneo->fresh()->estado);
    }

    public function test_un_torneo_finalizado_ya_no_cambia_de_estado(): void
    {
        $torneo = Torneo::factory()->finalizado()->create();

        $this->actingAs(Usuario::factory()->empleado()->create())
            ->postJson("/api/v1/admin/torneos/{$torneo->slug}/estado", ['estado' => 'cancelado'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('estado');
    }

    public function test_no_vuelve_a_borrador_con_equipos_inscritos(): void
    {
        $torneo = Torneo::factory()->create();
        Inscripcion::factory()->create(['torneo_id' => $torneo->id]);

        $this->actingAs(Usuario::factory()->empleado()->create())
            ->postJson("/api/v1/admin/torneos/{$torneo->slug}/estado", ['estado' => 'borrador'])
            ->assertUnprocessable();

        $this->assertSame(EstadoTorneo::InscripcionesAbiertas, $torneo->fresh()->estado);
    }

    public function test_cancelar_retira_las_inscripciones_y_devuelve_lo_pagado(): void
    {
        $torneo = Torneo::factory()->create();
        $equipo = Equipo::factory()->completo()->create();
        $inscripcion = Inscripcion::factory()->aprobada()->create([
            'torneo_id' => $torneo->id,
            'equipo_id' => $equipo->id,
            'monto_centavos' => 5_000_000,
        ]);

        $this->actingAs(Usuario::factory()->empleado()->create())
            ->postJson("/api/v1/admin/torneos/{$torneo->slug}/estado", ['estado' => 'cancelado'])
            ->assertOk()
            ->assertJsonPath('data.estado.valor', 'cancelado');

        $this->assertSame(EstadoInscripcion::Retirada, $inscripcion->fresh()->estado);
        $this->assertDatabaseHas('carteras', ['usuario_id' => $equipo->capitan_id, 'saldo_centavos' => 5_000_000]);
    }

    public function test_un_jugador_no_cambia_el_estado_de_un_torneo(): void
    {
        $torneo = Torneo::factory()->create();

        $this->actingAs(Usuario::factory()->create())
            ->postJson("/api/v1/admin/torneos/{$torneo->slug}/estado", ['estado' => 'cancelado'])
            ->assertForbidden();
    }

    /* ----------------------------------------------------------------- Baja */

    public function test_el_administrador_elimina_un_torneo_sin_pagos(): void
    {
        $torneo = Torneo::factory()->borrador()->create();

        $this->actingAs(Usuario::factory()->administrador()->create())
            ->deleteJson("/api/v1/admin/torneos/{$torneo->slug}")
            ->assertOk();

        $this->assertNull(Torneo::query()->find($torneo->id));
    }

    public function test_el_empleado_no_elimina_torneos(): void
    {
        $torneo = Torneo::factory()->borrador()->create();

        $this->actingAs(Usuario::factory()->empleado()->create())
            ->deleteJson("/api/v1/admin/torneos/{$torneo->slug}")
            ->assertForbidden();

        $this->assertNotNull(Torneo::query()->find($torneo->id));
    }

    public function test_no_se_elimina_un_torneo_con_inscripciones_pagadas(): void
    {
        $torneo = Torneo::factory()->create();
        Inscripcion::factory()->aprobada()->create(['torneo_id' => $torneo->id]);

        $this->actingAs(Usuario::factory()->administrador()->create())
            ->deleteJson("/api/v1/admin/torneos/{$torneo->slug}")
            ->assertUnprocessable();

        $this->assertNotNull(Torneo::query()->find($torneo->id));
    }

    public function test_no_se_elimina_un_torneo_que_ya_empezo(): void
    {
        $torneo = Torneo::factory()->enCurso()->create();

        $this->actingAs(Usuario::factory()->administrador()->create())
            ->deleteJson("/api/v1/admin/torneos/{$torneo->slug}")
            ->assertForbidden();

        $this->assertNotNull(Torneo::query()->find($torneo->id));
    }
}
