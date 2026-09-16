<?php

namespace Tests\Feature;

use App\Enumeraciones\EstadoInscripcion;
use App\Models\Equipo;
use App\Models\Inscripcion;
use App\Models\Jugador;
use App\Models\Torneo;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class InscripcionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_un_dueno_inscribe_su_equipo_y_queda_pendiente_de_pago(): void
    {
        $dueno = Usuario::factory()->dueno()->create();
        $equipo = $this->equipoConPlantilla($dueno);
        $torneo = Torneo::factory()->create(['costo_inscripcion_centavos' => 15_000_000]);

        $this->actingAs($dueno)
            ->postJson("/api/v1/panel/torneos/{$torneo->slug}/inscripciones", ['equipo_id' => $equipo->id])
            ->assertCreated()
            ->assertJsonPath('data.estado.valor', EstadoInscripcion::PendientePago->value)
            ->assertJsonPath('data.monto_centavos', 15_000_000)
            ->assertJsonPath('data.monto_formateado', '$ 150.000');

        $this->assertDatabaseHas('inscripciones', [
            'torneo_id' => $torneo->id,
            'equipo_id' => $equipo->id,
            'estado' => EstadoInscripcion::PendientePago->value,
        ]);
    }

    public function test_en_un_torneo_gratuito_la_inscripcion_pasa_directo_a_revision(): void
    {
        $dueno = Usuario::factory()->dueno()->create();
        $equipo = $this->equipoConPlantilla($dueno);
        $torneo = Torneo::factory()->gratuito()->create();

        $this->actingAs($dueno)
            ->postJson("/api/v1/panel/torneos/{$torneo->slug}/inscripciones", ['equipo_id' => $equipo->id])
            ->assertCreated()
            ->assertJsonPath('data.estado.valor', EstadoInscripcion::PendienteRevision->value);
    }

    public function test_no_se_inscribe_un_equipo_sin_el_minimo_de_jugadores(): void
    {
        $dueno = Usuario::factory()->dueno()->create();
        $equipo = Equipo::factory()->delDueno($dueno)->create();
        Jugador::factory()->delEquipo($equipo)->conDorsal(1)->create();

        $torneo = Torneo::factory()->create(['minimo_jugadores_por_equipo' => 7]);

        $this->actingAs($dueno)
            ->postJson("/api/v1/panel/torneos/{$torneo->slug}/inscripciones", ['equipo_id' => $equipo->id])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'El torneo exige al menos 7 jugadores activos y '.$equipo->nombre.' tiene 1.');

        $this->assertDatabaseCount('inscripciones', 0);
    }

    public function test_no_se_inscribe_un_equipo_ajeno(): void
    {
        $intruso = Usuario::factory()->dueno()->create();
        $equipo = $this->equipoConPlantilla(Usuario::factory()->dueno()->create());
        $torneo = Torneo::factory()->create();

        $this->actingAs($intruso)
            ->postJson("/api/v1/panel/torneos/{$torneo->slug}/inscripciones", ['equipo_id' => $equipo->id])
            ->assertForbidden();
    }

    public function test_no_se_inscribe_en_un_torneo_con_las_inscripciones_cerradas(): void
    {
        $dueno = Usuario::factory()->dueno()->create();
        $equipo = $this->equipoConPlantilla($dueno);
        $torneo = Torneo::factory()->inscripcionesCerradas()->create();

        $this->actingAs($dueno)
            ->postJson("/api/v1/panel/torneos/{$torneo->slug}/inscripciones", ['equipo_id' => $equipo->id])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Este torneo no tiene las inscripciones abiertas.');
    }

    public function test_no_se_inscribe_dos_veces_el_mismo_equipo(): void
    {
        $dueno = Usuario::factory()->dueno()->create();
        $equipo = $this->equipoConPlantilla($dueno);
        $torneo = Torneo::factory()->create();

        $this->actingAs($dueno)
            ->postJson("/api/v1/panel/torneos/{$torneo->slug}/inscripciones", ['equipo_id' => $equipo->id])
            ->assertCreated();

        $this->actingAs($dueno)
            ->postJson("/api/v1/panel/torneos/{$torneo->slug}/inscripciones", ['equipo_id' => $equipo->id])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Este equipo ya está inscrito en el torneo.');

        $this->assertDatabaseCount('inscripciones', 1);
    }

    public function test_no_se_inscribe_cuando_el_cupo_esta_lleno(): void
    {
        $torneo = Torneo::factory()->create(['cupo_maximo_equipos' => 1]);

        Inscripcion::factory()->aprobada()->create([
            'torneo_id' => $torneo->getKey(),
            'equipo_id' => Equipo::factory()->create()->getKey(),
        ]);

        $dueno = Usuario::factory()->dueno()->create();
        $equipo = $this->equipoConPlantilla($dueno);

        $this->actingAs($dueno)
            ->postJson("/api/v1/panel/torneos/{$torneo->slug}/inscripciones", ['equipo_id' => $equipo->id])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'El torneo ya tiene el cupo de equipos completo.');
    }

    public function test_el_dueno_retira_su_equipo_del_torneo(): void
    {
        $dueno = Usuario::factory()->dueno()->create();
        $equipo = $this->equipoConPlantilla($dueno);
        $inscripcion = Inscripcion::factory()->create([
            'torneo_id' => Torneo::factory()->create()->getKey(),
            'equipo_id' => $equipo->getKey(),
        ]);

        $this->actingAs($dueno)
            ->deleteJson("/api/v1/panel/inscripciones/{$inscripcion->id}")
            ->assertOk()
            ->assertJsonPath('data.estado.valor', EstadoInscripcion::Retirada->value);
    }

    public function test_la_organizacion_aprueba_una_inscripcion_pagada(): void
    {
        $empleado = Usuario::factory()->empleado()->create();
        $inscripcion = Inscripcion::factory()->pendienteDeRevision()->create([
            'torneo_id' => Torneo::factory()->create()->getKey(),
            'equipo_id' => Equipo::factory()->create()->getKey(),
        ]);

        $this->actingAs($empleado)
            ->postJson("/api/v1/admin/inscripciones/{$inscripcion->id}/aprobar", ['notas' => 'Papeles en orden'])
            ->assertOk()
            ->assertJsonPath('data.estado.valor', EstadoInscripcion::Aprobada->value);

        $this->assertSame($empleado->id, $inscripcion->refresh()->revisado_por_id);
    }

    public function test_no_se_aprueba_una_inscripcion_que_no_ha_pagado(): void
    {
        $inscripcion = Inscripcion::factory()->create([
            'torneo_id' => Torneo::factory()->create()->getKey(),
            'equipo_id' => Equipo::factory()->create()->getKey(),
        ]);

        $this->actingAs(Usuario::factory()->administrador()->create())
            ->postJson("/api/v1/admin/inscripciones/{$inscripcion->id}/aprobar")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Solo se aprueban las inscripciones que ya pagaron y están en revisión.');
    }

    public function test_rechazar_una_inscripcion_exige_un_motivo(): void
    {
        $inscripcion = Inscripcion::factory()->pendienteDeRevision()->create([
            'torneo_id' => Torneo::factory()->create()->getKey(),
            'equipo_id' => Equipo::factory()->create()->getKey(),
        ]);

        $admin = Usuario::factory()->administrador()->create();

        $this->actingAs($admin)
            ->postJson("/api/v1/admin/inscripciones/{$inscripcion->id}/rechazar")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('notas');

        $this->actingAs($admin)
            ->postJson("/api/v1/admin/inscripciones/{$inscripcion->id}/rechazar", [
                'notas' => 'Faltan los documentos de tres jugadores.',
            ])
            ->assertOk()
            ->assertJsonPath('data.estado.valor', EstadoInscripcion::Rechazada->value);
    }

    public function test_un_dueno_no_puede_revisar_inscripciones(): void
    {
        $inscripcion = Inscripcion::factory()->pendienteDeRevision()->create([
            'torneo_id' => Torneo::factory()->create()->getKey(),
            'equipo_id' => Equipo::factory()->create()->getKey(),
        ]);

        $this->actingAs(Usuario::factory()->dueno()->create())
            ->postJson("/api/v1/admin/inscripciones/{$inscripcion->id}/aprobar")
            ->assertForbidden();
    }

    private function equipoConPlantilla(Usuario $dueno, int $jugadores = 8): Equipo
    {
        $equipo = Equipo::factory()->delDueno($dueno)->create();

        foreach (range(1, $jugadores) as $dorsal) {
            Jugador::factory()->delEquipo($equipo)->conDorsal($dorsal)->create();
        }

        return $equipo;
    }
}
