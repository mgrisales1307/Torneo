<?php

namespace Tests\Feature;

use App\Models\Equipo;
use App\Models\Inscripcion;
use App\Models\Torneo;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Rasgos\CreaImagenesDePrueba;
use Tests\TestCase;

class EquiposDelDuenoTest extends TestCase
{
    use CreaImagenesDePrueba, LazilyRefreshDatabase;

    public function test_un_dueno_crea_su_equipo(): void
    {
        $dueno = Usuario::factory()->dueno()->create();

        $respuesta = $this->actingAs($dueno)->postJson('/api/v1/panel/equipos', [
            'nombre' => 'Atlético Universidad',
            'abreviatura' => 'atu',
            'ciudad' => 'Medellín',
            'color_primario' => '#0B1D17',
        ]);

        $respuesta->assertCreated()
            ->assertJsonPath('data.nombre', 'Atlético Universidad')
            // La abreviatura se guarda siempre en mayúsculas.
            ->assertJsonPath('data.abreviatura', 'ATU')
            ->assertJsonPath('data.slug', 'atletico-universidad');

        $this->assertDatabaseHas('equipos', [
            'nombre' => 'Atlético Universidad',
            'dueno_id' => $dueno->id,
        ]);
    }

    public function test_el_listado_solo_trae_los_equipos_propios(): void
    {
        $dueno = Usuario::factory()->dueno()->create();
        $mio = Equipo::factory()->delDueno($dueno)->create();
        Equipo::factory()->create();

        $respuesta = $this->actingAs($dueno)->getJson('/api/v1/panel/equipos')->assertOk();

        $this->assertCount(1, $respuesta->json('data'));
        $this->assertSame($mio->id, $respuesta->json('data.0.id'));
    }

    public function test_un_dueno_no_puede_ver_ni_editar_el_equipo_de_otro(): void
    {
        $intruso = Usuario::factory()->dueno()->create();
        $ajeno = Equipo::factory()->create();

        $this->actingAs($intruso)->getJson("/api/v1/panel/equipos/{$ajeno->slug}")->assertForbidden();

        $this->actingAs($intruso)
            ->putJson("/api/v1/panel/equipos/{$ajeno->slug}", ['nombre' => 'Secuestrado'])
            ->assertForbidden();

        $this->actingAs($intruso)->deleteJson("/api/v1/panel/equipos/{$ajeno->slug}")->assertForbidden();

        $this->assertDatabaseHas('equipos', ['id' => $ajeno->id, 'nombre' => $ajeno->nombre]);
    }

    public function test_un_espectador_no_puede_crear_equipos(): void
    {
        $this->actingAs(Usuario::factory()->create())
            ->postJson('/api/v1/panel/equipos', ['nombre' => 'Equipo Fantasma', 'abreviatura' => 'EFA'])
            ->assertForbidden();
    }

    public function test_el_dueno_actualiza_su_equipo(): void
    {
        $dueno = Usuario::factory()->dueno()->create();
        $equipo = Equipo::factory()->delDueno($dueno)->create();

        $this->actingAs($dueno)->putJson("/api/v1/panel/equipos/{$equipo->slug}", [
            'estadio' => 'Cancha La 70',
            'color_secundario' => '#EAB857',
        ])->assertOk()->assertJsonPath('data.estadio', 'Cancha La 70');

        // El slug no cambia al editar: los enlaces que ya se compartieron siguen
        // funcionando.
        $this->assertSame($equipo->slug, $equipo->refresh()->slug);
    }

    public function test_un_color_mal_escrito_se_rechaza(): void
    {
        $dueno = Usuario::factory()->dueno()->create();
        $equipo = Equipo::factory()->delDueno($dueno)->create();

        $this->actingAs($dueno)
            ->putJson("/api/v1/panel/equipos/{$equipo->slug}", ['color_primario' => 'verde'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('color_primario');
    }

    public function test_el_dueno_sube_el_escudo_de_su_equipo(): void
    {
        Storage::fake('public');

        $dueno = Usuario::factory()->dueno()->create();
        $equipo = Equipo::factory()->delDueno($dueno)->create();

        $respuesta = $this->actingAs($dueno)->post("/api/v1/panel/equipos/{$equipo->slug}/imagenes", [
            'tipo' => 'escudo',
            'imagen' => $this->imagenDePrueba(),
        ], ['Accept' => 'application/json']);

        $respuesta->assertOk();

        $ruta = $equipo->refresh()->escudo_ruta;

        $this->assertNotNull($ruta);
        Storage::disk('public')->assertExists($ruta);
        $this->assertSame('/storage/'.$ruta, $respuesta->json('data.imagenes.escudo_url'));
    }

    public function test_al_reemplazar_el_escudo_se_borra_el_anterior(): void
    {
        Storage::fake('public');

        $dueno = Usuario::factory()->dueno()->create();
        $equipo = Equipo::factory()->delDueno($dueno)->create();

        $this->actingAs($dueno)->post("/api/v1/panel/equipos/{$equipo->slug}/imagenes", [
            'tipo' => 'escudo',
            'imagen' => $this->imagenDePrueba('primero.png'),
        ], ['Accept' => 'application/json'])->assertOk();

        $primera = $equipo->refresh()->escudo_ruta;

        $this->actingAs($dueno)->post("/api/v1/panel/equipos/{$equipo->slug}/imagenes", [
            'tipo' => 'uniforme_local',
            'imagen' => $this->imagenDePrueba('uniforme.png'),
        ], ['Accept' => 'application/json'])->assertOk();

        $this->actingAs($dueno)->post("/api/v1/panel/equipos/{$equipo->slug}/imagenes", [
            'tipo' => 'escudo',
            'imagen' => $this->imagenDePrueba('segundo.png'),
        ], ['Accept' => 'application/json'])->assertOk();

        $segunda = $equipo->refresh()->escudo_ruta;

        $this->assertNotSame($primera, $segunda);
        Storage::disk('public')->assertMissing($primera);
        Storage::disk('public')->assertExists($segunda);
        Storage::disk('public')->assertExists($equipo->uniforme_local_ruta);
    }

    public function test_un_archivo_que_no_es_imagen_se_rechaza_aunque_se_llame_png(): void
    {
        Storage::fake('public');

        $dueno = Usuario::factory()->dueno()->create();
        $equipo = Equipo::factory()->delDueno($dueno)->create();

        $this->actingAs($dueno)->post("/api/v1/panel/equipos/{$equipo->slug}/imagenes", [
            'tipo' => 'escudo',
            'imagen' => $this->archivoQueNoEsImagen(),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('imagen');

        $this->assertNull($equipo->refresh()->escudo_ruta);
    }

    public function test_no_se_elimina_un_equipo_inscrito_y_pagando_en_un_torneo(): void
    {
        $dueno = Usuario::factory()->dueno()->create();
        $equipo = Equipo::factory()->delDueno($dueno)->create();

        Inscripcion::factory()->aprobada()->create([
            'torneo_id' => Torneo::factory()->enCurso()->create()->getKey(),
            'equipo_id' => $equipo->getKey(),
        ]);

        $this->actingAs($dueno)->deleteJson("/api/v1/panel/equipos/{$equipo->slug}")->assertForbidden();

        $this->assertNotSoftDeleted($equipo);
    }

    public function test_el_dueno_elimina_un_equipo_sin_compromisos(): void
    {
        $dueno = Usuario::factory()->dueno()->create();
        $equipo = Equipo::factory()->delDueno($dueno)->create();

        $this->actingAs($dueno)->deleteJson("/api/v1/panel/equipos/{$equipo->slug}")->assertOk();

        $this->assertSoftDeleted($equipo);

        // El slug queda liberado para que el nombre se pueda volver a usar.
        $this->assertNotSame($equipo->slug, $equipo->fresh()->slug);
    }
}
