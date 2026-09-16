<?php

namespace Tests\Feature;

use App\Enumeraciones\EstadoJugador;
use App\Enumeraciones\PosicionJugador;
use App\Models\Equipo;
use App\Models\Jugador;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class JugadoresTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Usuario $dueno;

    private Equipo $equipo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dueno = Usuario::factory()->dueno()->create();
        $this->equipo = Equipo::factory()->delDueno($this->dueno)->create();
    }

    public function test_el_dueno_suma_un_jugador_a_su_plantilla(): void
    {
        $respuesta = $this->actingAs($this->dueno)
            ->postJson("/api/v1/panel/equipos/{$this->equipo->slug}/jugadores", $this->datosDeJugador());

        $respuesta->assertCreated()
            ->assertJsonPath('data.nombre_completo', 'Juan David Muñoz Salazar')
            ->assertJsonPath('data.numero_camiseta', 10)
            ->assertJsonPath('data.posicion.valor', PosicionJugador::Delantero->value)
            // En el panel sí se ven los datos personales.
            ->assertJsonPath('data.documento_identidad', '1023456789');

        $this->assertDatabaseHas('jugadores', [
            'equipo_id' => $this->equipo->id,
            'documento_identidad' => '1023456789',
        ]);
    }

    public function test_no_se_repite_el_dorsal_dentro_del_equipo(): void
    {
        Jugador::factory()->delEquipo($this->equipo)->conDorsal(10)->create();

        $this->actingAs($this->dueno)
            ->postJson("/api/v1/panel/equipos/{$this->equipo->slug}/jugadores", $this->datosDeJugador())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('numero_camiseta');
    }

    public function test_el_mismo_dorsal_si_puede_repetirse_en_equipos_distintos(): void
    {
        Jugador::factory()->delEquipo(Equipo::factory()->create())->conDorsal(10)->create();

        $this->actingAs($this->dueno)
            ->postJson("/api/v1/panel/equipos/{$this->equipo->slug}/jugadores", $this->datosDeJugador())
            ->assertCreated();
    }

    public function test_no_se_repite_el_documento_dentro_del_equipo(): void
    {
        Jugador::factory()->delEquipo($this->equipo)->conDorsal(5)->create([
            'documento_identidad' => '1023456789',
        ]);

        $this->actingAs($this->dueno)
            ->postJson("/api/v1/panel/equipos/{$this->equipo->slug}/jugadores", $this->datosDeJugador())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('documento_identidad');
    }

    public function test_se_rechaza_un_jugador_demasiado_joven(): void
    {
        $this->actingAs($this->dueno)->postJson(
            "/api/v1/panel/equipos/{$this->equipo->slug}/jugadores",
            [...$this->datosDeJugador(), 'fecha_nacimiento' => now()->subYears(9)->toDateString()],
        )->assertUnprocessable()->assertJsonValidationErrors('fecha_nacimiento');
    }

    public function test_dar_de_baja_a_un_jugador_libera_su_dorsal(): void
    {
        $jugador = Jugador::factory()->delEquipo($this->equipo)->conDorsal(10)->create();

        $this->actingAs($this->dueno)
            ->deleteJson("/api/v1/panel/jugadores/{$jugador->id}")
            ->assertOk();

        $this->assertSoftDeleted($jugador);
        $this->assertNull($jugador->fresh()->numero_camiseta);

        // Y el dorsal vuelve a estar disponible para otro jugador.
        $this->actingAs($this->dueno)
            ->postJson("/api/v1/panel/equipos/{$this->equipo->slug}/jugadores", $this->datosDeJugador())
            ->assertCreated();
    }

    public function test_solo_hay_un_capitan_por_equipo(): void
    {
        $primero = Jugador::factory()->delEquipo($this->equipo)->conDorsal(5)->capitan()->create();

        $this->actingAs($this->dueno)->postJson(
            "/api/v1/panel/equipos/{$this->equipo->slug}/jugadores",
            [...$this->datosDeJugador(), 'es_capitan' => true],
        )->assertCreated();

        $this->assertFalse($primero->refresh()->es_capitan);
        $this->assertSame(1, $this->equipo->jugadores()->where('es_capitan', true)->count());
    }

    public function test_el_dueno_edita_el_estado_de_un_jugador(): void
    {
        $jugador = Jugador::factory()->delEquipo($this->equipo)->conDorsal(7)->create();

        $this->actingAs($this->dueno)->putJson("/api/v1/panel/jugadores/{$jugador->id}", [
            'estado' => EstadoJugador::Lesionado->value,
        ])->assertOk()->assertJsonPath('data.estado.valor', EstadoJugador::Lesionado->value);
    }

    public function test_nadie_puede_tocar_la_plantilla_de_un_equipo_ajeno(): void
    {
        $intruso = Usuario::factory()->dueno()->create();
        $jugador = Jugador::factory()->delEquipo($this->equipo)->conDorsal(9)->create();

        $this->actingAs($intruso)
            ->postJson("/api/v1/panel/equipos/{$this->equipo->slug}/jugadores", $this->datosDeJugador())
            ->assertForbidden();

        $this->actingAs($intruso)
            ->putJson("/api/v1/panel/jugadores/{$jugador->id}", ['estado' => EstadoJugador::Suspendido->value])
            ->assertForbidden();

        $this->actingAs($intruso)
            ->deleteJson("/api/v1/panel/jugadores/{$jugador->id}")
            ->assertForbidden();

        $this->assertNotSoftDeleted($jugador);
    }

    /**
     * @return array<string, mixed>
     */
    private function datosDeJugador(): array
    {
        return [
            'nombres' => 'Juan David',
            'apellidos' => 'Muñoz Salazar',
            'documento_identidad' => '1023456789',
            'fecha_nacimiento' => now()->subYears(22)->toDateString(),
            'posicion' => PosicionJugador::Delantero->value,
            'numero_camiseta' => 10,
        ];
    }
}
