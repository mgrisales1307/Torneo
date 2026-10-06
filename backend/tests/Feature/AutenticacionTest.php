<?php

namespace Tests\Feature;

use App\Enumeraciones\RolUsuario;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AutenticacionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_una_persona_se_registra_y_queda_con_la_sesion_abierta_como_jugadora(): void
    {
        $respuesta = $this->desdeElFrontend()->postJson('/api/v1/registro', [
            'apodo' => 'LauraGG',
            'nombre' => 'Laura Gómez',
            'correo' => 'Laura@Equipo.com',
            'clave' => 'tornea2026',
            'clave_confirmation' => 'tornea2026',
        ]);

        $respuesta->assertCreated()
            ->assertJsonPath('usuario.nombre', 'Laura Gómez')
            ->assertJsonPath('usuario.apodo', 'LauraGG')
            ->assertJsonPath('usuario.rol.valor', RolUsuario::Jugador->value);

        // El correo se guarda normalizado en minúsculas.
        $usuario = Usuario::query()->where('correo', 'laura@equipo.com')->firstOrFail();

        $this->assertAuthenticatedAs($usuario);
        $this->assertNotSame('tornea2026', $usuario->clave);
    }

    public function test_el_registro_rechaza_un_correo_repetido(): void
    {
        Usuario::factory()->create(['correo' => 'repetido@equipo.com']);

        $this->desdeElFrontend()->postJson('/api/v1/registro', [
            'apodo' => 'OtraPersona',
            'nombre' => 'Otra Persona',
            'correo' => 'repetido@equipo.com',
            'clave' => 'tornea2026',
            'clave_confirmation' => 'tornea2026',
        ])->assertUnprocessable()->assertJsonValidationErrors('correo');
    }

    public function test_el_registro_exige_una_clave_con_letras_y_numeros(): void
    {
        $this->desdeElFrontend()->postJson('/api/v1/registro', [
            'apodo' => 'LauraGG',
            'nombre' => 'Laura Gómez',
            'correo' => 'laura@equipo.com',
            'clave' => 'corta',
            'clave_confirmation' => 'corta',
        ])->assertUnprocessable()->assertJsonValidationErrors('clave');
    }

    public function test_el_apodo_es_unico_y_sin_espacios(): void
    {
        Usuario::factory()->create(['apodo' => 'Tomado']);

        $this->desdeElFrontend()->postJson('/api/v1/registro', [
            'apodo' => 'Tomado',
            'nombre' => 'Laura Gómez',
            'correo' => 'laura@equipo.com',
            'clave' => 'tornea2026',
            'clave_confirmation' => 'tornea2026',
        ])->assertUnprocessable()->assertJsonValidationErrors('apodo');

        $this->desdeElFrontend()->postJson('/api/v1/registro', [
            'apodo' => 'con espacio',
            'nombre' => 'Laura Gómez',
            'correo' => 'laura@equipo.com',
            'clave' => 'tornea2026',
            'clave_confirmation' => 'tornea2026',
        ])->assertUnprocessable()->assertJsonValidationErrors('apodo');
    }

    public function test_las_credenciales_correctas_abren_la_sesion(): void
    {
        $usuario = Usuario::factory()->create(['correo' => 'capitan@equipo.com']);

        $this->desdeElFrontend()->postJson('/api/v1/ingreso', [
            'correo' => 'capitan@equipo.com',
            'clave' => 'password',
            'recordarme' => true,
        ])->assertOk()->assertJsonPath('usuario.id', $usuario->id);

        $this->assertAuthenticatedAs($usuario);
    }

    public function test_las_credenciales_incorrectas_devuelven_422_sin_decir_si_el_correo_existe(): void
    {
        Usuario::factory()->create(['correo' => 'capitan@equipo.com']);

        $conClaveMala = $this->desdeElFrontend()->postJson('/api/v1/ingreso', [
            'correo' => 'capitan@equipo.com',
            'clave' => 'incorrecta',
        ])->assertUnprocessable()->assertJsonValidationErrors('correo');

        $conCorreoInexistente = $this->desdeElFrontend()->postJson('/api/v1/ingreso', [
            'correo' => 'nadie@equipo.com',
            'clave' => 'incorrecta',
        ])->assertUnprocessable()->assertJsonValidationErrors('correo');

        // El mismo mensaje en los dos casos: si difirieran, se podría averiguar
        // qué correos tienen cuenta.
        $this->assertSame(
            $conClaveMala->json('errors.correo'),
            $conCorreoInexistente->json('errors.correo'),
        );

        $this->assertGuest();
    }

    public function test_el_perfil_exige_sesion(): void
    {
        $this->getJson('/api/v1/perfil')->assertUnauthorized();
    }

    public function test_con_sesion_se_consulta_el_perfil_y_se_cierra_la_sesion(): void
    {
        $usuario = Usuario::factory()->create();

        $this->actingAs($usuario)->getJson('/api/v1/perfil')
            ->assertOk()
            ->assertJsonPath('usuario.correo', $usuario->correo)
            ->assertJsonPath('usuario.rol.valor', RolUsuario::Jugador->value)
            // La contraseña no sale nunca en la respuesta.
            ->assertJsonMissingPath('usuario.clave');

        $this->actingAs($usuario)->desdeElFrontend()->postJson('/api/v1/salida')->assertOk();
        $this->assertGuest('web');
    }

    public function test_el_ingreso_se_bloquea_tras_varios_intentos_fallidos(): void
    {
        Usuario::factory()->create(['correo' => 'capitan@equipo.com']);

        foreach (range(1, 5) as $intento) {
            $this->desdeElFrontend()->postJson('/api/v1/ingreso', [
                'correo' => 'capitan@equipo.com',
                'clave' => 'incorrecta',
            ])->assertUnprocessable();
        }

        $this->desdeElFrontend()->postJson('/api/v1/ingreso', [
            'correo' => 'capitan@equipo.com',
            'clave' => 'incorrecta',
        ])->assertStatus(429);
    }
}
