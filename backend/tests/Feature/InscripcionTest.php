<?php

namespace Tests\Feature;

use App\Enumeraciones\EstadoInscripcion;
use App\Enumeraciones\Juego;
use App\Models\Equipo;
use App\Models\Inscripcion;
use App\Models\Torneo;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class InscripcionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_el_capitan_inscribe_su_equipo_y_queda_pendiente_de_pago(): void
    {
        $equipo = Equipo::factory()->completo()->create();
        $torneo = Torneo::factory()->create(['costo_inscripcion_centavos' => 5_000_000]);

        $this->actingAs($equipo->capitan)
            ->postJson("/api/v1/panel/torneos/{$torneo->slug}/inscripciones", ['equipo_id' => $equipo->id])
            ->assertCreated()
            ->assertJsonPath('data.estado.valor', EstadoInscripcion::PendientePago->value)
            ->assertJsonPath('data.monto_formateado', '$ 50.000');
    }

    public function test_en_un_torneo_gratuito_la_inscripcion_pasa_directo_a_revision(): void
    {
        $equipo = Equipo::factory()->completo()->create();
        $torneo = Torneo::factory()->gratuito()->create();

        $this->actingAs($equipo->capitan)
            ->postJson("/api/v1/panel/torneos/{$torneo->slug}/inscripciones", ['equipo_id' => $equipo->id])
            ->assertCreated()
            ->assertJsonPath('data.estado.valor', EstadoInscripcion::PendienteRevision->value);
    }

    public function test_solo_el_capitan_inscribe(): void
    {
        $equipo = Equipo::factory()->completo()->create();
        $miembro = $equipo->miembros()->whereKeyNot($equipo->capitan_id)->firstOrFail();
        $torneo = Torneo::factory()->create();

        $this->actingAs($miembro)
            ->postJson("/api/v1/panel/torneos/{$torneo->slug}/inscripciones", ['equipo_id' => $equipo->id])
            ->assertForbidden();
    }

    public function test_un_equipo_no_se_inscribe_en_un_torneo_de_otro_juego(): void
    {
        $equipo = Equipo::factory()->deJuego(Juego::Dota2)->completo()->create();
        $torneo = Torneo::factory()->create();

        $this->actingAs($equipo->capitan)
            ->postJson("/api/v1/panel/torneos/{$torneo->slug}/inscripciones", ['equipo_id' => $equipo->id])
            ->assertUnprocessable()
            ->assertJsonPath('message', "Este torneo es de Valorant y {$equipo->nombre} juega Dota 2.");
    }

    public function test_hace_falta_la_plantilla_titular_completa(): void
    {
        $equipo = Equipo::factory()->create();
        $torneo = Torneo::factory()->create();

        $this->actingAs($equipo->capitan)
            ->postJson("/api/v1/panel/torneos/{$torneo->slug}/inscripciones", ['equipo_id' => $equipo->id])
            ->assertUnprocessable()
            ->assertJsonPath('message', "{$equipo->nombre} necesita al menos 5 jugadores y tiene 1.");
    }

    public function test_todos_necesitan_su_cuenta_del_juego_vinculada(): void
    {
        $equipo = Equipo::factory()->completo()->create();
        $sinCuenta = Usuario::factory()->create(['apodo' => 'SinCuenta']);
        $equipo->miembros()->attach($sinCuenta->getKey(), ['es_suplente' => true]);
        $torneo = Torneo::factory()->create();

        $this->actingAs($equipo->capitan)
            ->postJson("/api/v1/panel/torneos/{$torneo->slug}/inscripciones", ['equipo_id' => $equipo->id])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Falta vincular el Riot ID de: SinCuenta.');
    }

    public function test_nadie_juega_el_mismo_torneo_con_dos_equipos(): void
    {
        $torneo = Torneo::factory()->create();
        $primero = Equipo::factory()->completo()->create();
        Inscripcion::factory()->create(['torneo_id' => $torneo->id, 'equipo_id' => $primero->id]);

        $doble = $primero->miembros()->whereKeyNot($primero->capitan_id)->firstOrFail();
        $segundo = Equipo::factory()->completo()->create();
        $segundo->miembros()->attach($doble->getKey(), ['es_suplente' => true]);

        $this->actingAs($segundo->capitan)
            ->postJson("/api/v1/panel/torneos/{$torneo->slug}/inscripciones", ['equipo_id' => $segundo->id])
            ->assertUnprocessable()
            ->assertJsonPath('message', "{$doble->apodo} ya juega este torneo con {$primero->nombre}.");
    }

    public function test_no_se_inscribe_con_las_inscripciones_cerradas_ni_con_el_cupo_lleno(): void
    {
        $equipo = Equipo::factory()->completo()->create();
        $cerrado = Torneo::factory()->inscripcionesCerradas()->create();

        $this->actingAs($equipo->capitan)
            ->postJson("/api/v1/panel/torneos/{$cerrado->slug}/inscripciones", ['equipo_id' => $equipo->id])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Este torneo no tiene las inscripciones abiertas.');

        $lleno = Torneo::factory()->create(['cupo_maximo_equipos' => 2]);
        Inscripcion::factory()->count(2)->aprobada()->create(['torneo_id' => $lleno->id]);

        $this->actingAs($equipo->capitan)
            ->postJson("/api/v1/panel/torneos/{$lleno->slug}/inscripciones", ['equipo_id' => $equipo->id])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'El torneo ya tiene el cupo de equipos completo.');
    }

    public function test_el_check_in_abre_antes_del_inicio_y_solo_para_aprobados(): void
    {
        $equipo = Equipo::factory()->completo()->create();
        $torneo = Torneo::factory()->conCheckIn(30)->create(['inicia_en' => now()->addHours(2)]);
        $inscripcion = Inscripcion::factory()->aprobada()->create(['torneo_id' => $torneo->id, 'equipo_id' => $equipo->id]);

        $abre = now()->addHours(2)->subMinutes(30);

        $this->actingAs($equipo->capitan)
            ->postJson("/api/v1/panel/inscripciones/{$inscripcion->id}/check-in")
            ->assertUnprocessable()
            ->assertJsonPath('message', sprintf('El check-in abre el %s a las %s.', $abre->format('d/m'), $abre->format('H:i')));

        $this->travel(100)->minutes();

        $this->actingAs($equipo->capitan)
            ->postJson("/api/v1/panel/inscripciones/{$inscripcion->id}/check-in")
            ->assertOk();

        $this->assertNotNull($inscripcion->refresh()->check_in_en);

        $pendiente = Inscripcion::factory()->pendienteDeRevision()->create(['torneo_id' => $torneo->id]);

        $this->actingAs($pendiente->equipo->capitan)
            ->postJson("/api/v1/panel/inscripciones/{$pendiente->id}/check-in")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Solo hacen check-in los equipos con la inscripción aprobada.');
    }

    public function test_la_organizacion_aprueba_y_rechaza(): void
    {
        $organizador = Usuario::factory()->empleado()->create();
        $torneo = Torneo::factory()->create();
        [$una, $otra] = Inscripcion::factory()->count(2)->pendienteDeRevision()->create(['torneo_id' => $torneo->id]);

        $this->actingAs($organizador)
            ->postJson("/api/v1/admin/inscripciones/{$una->id}/aprobar")
            ->assertOk()
            ->assertJsonPath('data.estado.valor', EstadoInscripcion::Aprobada->value);

        $this->actingAs($organizador)
            ->postJson("/api/v1/admin/inscripciones/{$otra->id}/rechazar", ['notas' => 'El Riot ID no coincide.'])
            ->assertOk()
            ->assertJsonPath('data.notas_revision', 'El Riot ID no coincide.');
    }
}
