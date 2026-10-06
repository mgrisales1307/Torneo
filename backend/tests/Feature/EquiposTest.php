<?php

namespace Tests\Feature;

use App\Enumeraciones\Juego;
use App\Models\Equipo;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Rasgos\ArmaTorneos;
use Tests\Rasgos\CreaImagenesDePrueba;
use Tests\TestCase;

class EquiposTest extends TestCase
{
    use ArmaTorneos, CreaImagenesDePrueba, LazilyRefreshDatabase;

    public function test_quien_crea_el_equipo_queda_como_capitan_y_primer_miembro(): void
    {
        $capitan = Usuario::factory()->create();

        $this->actingAs($capitan)->postJson('/api/v1/panel/equipos', [
            'juego' => 'valorant',
            'nombre' => 'Cóndores Andinos',
            'abreviatura' => 'cda',
        ])->assertCreated()
            ->assertJsonPath('data.abreviatura', 'CDA')
            ->assertJsonPath('data.soy_capitan', true)
            ->assertJsonPath('data.miembros.0.apodo', $capitan->apodo)
            ->assertJsonPath('data.miembros.0.es_capitan', true);

        $this->assertNotNull(Equipo::query()->firstOrFail()->codigo_invitacion);
    }

    public function test_con_el_codigo_se_entra_al_equipo_y_pasado_el_cupo_de_titulares_se_entra_de_suplente(): void
    {
        $equipo = Equipo::factory()->completo()->create();
        $nuevo = Usuario::factory()->create();

        $this->actingAs($nuevo)
            ->postJson('/api/v1/panel/unirse', ['codigo' => strtolower($equipo->codigo_invitacion)])
            ->assertOk();

        $this->assertTrue((bool) $equipo->miembros()->whereKey($nuevo->getKey())->firstOrFail()->pivot->es_suplente);
    }

    public function test_no_se_entra_a_un_equipo_completo_ni_con_un_codigo_que_no_existe(): void
    {
        $equipo = Equipo::factory()->completo()->create();
        $equipo->miembros()->attach(Usuario::factory()->count(2)->create()->modelKeys(), ['es_suplente' => true]);

        $this->actingAs(Usuario::factory()->create())
            ->postJson('/api/v1/panel/unirse', ['codigo' => $equipo->codigo_invitacion])
            ->assertUnprocessable()
            ->assertJsonPath('message', "{$equipo->nombre} está completo: 5 titulares y 2 suplentes.");

        $this->actingAs(Usuario::factory()->create())
            ->postJson('/api/v1/panel/unirse', ['codigo' => 'ZZZZZZZZ'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('codigo');
    }

    public function test_el_codigo_solo_lo_ven_los_miembros(): void
    {
        $equipo = Equipo::factory()->completo()->create();

        $this->getJson("/api/v1/equipos/{$equipo->slug}")
            ->assertOk()
            ->assertJsonMissingPath('data.codigo_invitacion');

        $this->actingAs(Usuario::factory()->create())
            ->getJson("/api/v1/panel/equipos/{$equipo->slug}")
            ->assertForbidden();

        $this->actingAs($equipo->capitan)
            ->getJson("/api/v1/panel/equipos/{$equipo->slug}")
            ->assertOk()
            ->assertJsonPath('data.codigo_invitacion', $equipo->codigo_invitacion);
    }

    public function test_el_capitan_cambia_suplentes_cede_la_capitania_y_saca_miembros(): void
    {
        $equipo = Equipo::factory()->completo()->create();
        $capitan = $equipo->capitan;
        $otro = $equipo->miembros()->whereKeyNot($capitan->getKey())->firstOrFail();

        $this->actingAs($capitan)
            ->patchJson("/api/v1/panel/equipos/{$equipo->slug}/miembros/{$otro->id}", ['es_suplente' => true])
            ->assertOk();

        $this->actingAs($capitan)
            ->patchJson("/api/v1/panel/equipos/{$equipo->slug}/miembros/{$otro->id}", ['capitan' => true])
            ->assertOk();

        $this->assertSame($otro->getKey(), $equipo->refresh()->capitan_id);

        // El antiguo capitán ya no gestiona, pero sí puede irse.
        $this->actingAs($capitan)
            ->deleteJson("/api/v1/panel/equipos/{$equipo->slug}/miembros/{$otro->id}")
            ->assertForbidden();

        $this->actingAs($capitan)
            ->deleteJson("/api/v1/panel/equipos/{$equipo->slug}/miembros/{$capitan->id}")
            ->assertOk();

        $this->assertFalse($equipo->tieneMiembro($capitan));
    }

    public function test_el_capitan_no_puede_irse_sin_ceder_la_capitania(): void
    {
        $equipo = Equipo::factory()->completo()->create();

        $this->actingAs($equipo->capitan)
            ->deleteJson("/api/v1/panel/equipos/{$equipo->slug}/miembros/{$equipo->capitan_id}")
            ->assertUnprocessable();
    }

    public function test_la_plantilla_no_cambia_mientras_el_equipo_juega_un_torneo(): void
    {
        $torneo = $this->torneoConEquipos(4);
        $this->iniciar($torneo);
        $equipo = $torneo->participantes()->firstOrFail()->equipo;

        $this->actingAs(Usuario::factory()->create())
            ->postJson('/api/v1/panel/unirse', ['codigo' => $equipo->codigo_invitacion])
            ->assertUnprocessable()
            ->assertJsonPath('message', "{$equipo->nombre} está jugando un torneo: la plantilla no cambia hasta que termine.");
    }

    public function test_el_juego_de_un_equipo_no_se_cambia(): void
    {
        $equipo = Equipo::factory()->create();

        $this->actingAs($equipo->capitan)
            ->patchJson("/api/v1/panel/equipos/{$equipo->slug}", ['juego' => Juego::Dota2->value])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('juego');
    }

    public function test_el_capitan_sube_el_logo(): void
    {
        $equipo = Equipo::factory()->create();

        $respuesta = $this->actingAs($equipo->capitan)
            ->post("/api/v1/panel/equipos/{$equipo->slug}/logo", ['logo' => $this->imagenDePrueba()], ['Accept' => 'application/json'])
            ->assertOk();

        $this->assertStringStartsWith('/storage/logos/', $respuesta->json('data.logo_url'));

        $this->actingAs($equipo->capitan)
            ->post("/api/v1/panel/equipos/{$equipo->slug}/logo", ['logo' => $this->archivoQueNoEsImagen()], ['Accept' => 'application/json'])
            ->assertUnprocessable();
    }

    public function test_mis_equipos_son_los_equipos_donde_juego(): void
    {
        $jugador = Usuario::factory()->create();
        $propio = Equipo::factory()->conCapitan($jugador)->create();
        $ajeno = Equipo::factory()->completo()->create();
        $ajeno->miembros()->attach($jugador->getKey(), ['es_suplente' => true]);
        Equipo::factory()->create();

        $this->actingAs($jugador)->getJson('/api/v1/panel/equipos')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertTrue($propio->esCapitan($jugador));
    }
}
