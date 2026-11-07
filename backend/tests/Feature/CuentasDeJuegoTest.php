<?php

namespace Tests\Feature;

use App\Enumeraciones\Juego;
use App\Models\CuentaDeJuego;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CuentasDeJuegoTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * La vinculación es simulada: no se verifica con el juego, así que vale
     * cualquier texto razonable (de 3 a 60 caracteres).
     *
     * @return array<string, array{string, string, bool}>
     */
    public static function cuentas(): array
    {
        return [
            'Riot ID con el formato clásico' => ['valorant', 'Parcero#LAN', true],
            'Riot ID sin etiqueta' => ['valorant', 'Parcero', true],
            'Riot ID en LoL con espacios' => ['lol', 'La Mona#COL1', true],
            'Steam ID de 17 cifras' => ['dota2', '76561198012345678', true],
            'Steam ID corto' => ['dota2', '12345', true],
            'nombre de PUBG con espacios' => ['pubg', 'El Paisa', true],
            'UID de Free Fire con letras' => ['freefire', 'abc123456', true],
            'texto con tildes y símbolos' => ['valorant', 'Ñandú_99!', true],
            'justo el mínimo' => ['pubg', 'abc', true],
            'justo el máximo' => ['pubg', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', true],
            'demasiado corto' => ['valorant', 'ab', false],
            'vacío' => ['valorant', '', false],
            'solo espacios' => ['dota2', '    ', false],
            'demasiado largo' => ['pubg', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', false],
        ];
    }

    #[DataProvider('cuentas')]
    public function test_la_cuenta_acepta_cualquier_texto_razonable_sin_verificar_el_formato(string $juego, string $identificador, bool $valida): void
    {
        $respuesta = $this->actingAs(Usuario::factory()->create())
            ->putJson("/api/v1/panel/cuentas/$juego", ['identificador' => $identificador]);

        $valida
            ? $respuesta->assertOk()->assertJsonPath('data.identificador', $identificador)
            : $respuesta->assertUnprocessable()->assertJsonValidationErrors('identificador');
    }

    public function test_los_espacios_de_los_extremos_no_se_guardan(): void
    {
        $this->actingAs(Usuario::factory()->create())
            ->putJson('/api/v1/panel/cuentas/valorant', ['identificador' => '   Parcero   '])
            ->assertOk()
            ->assertJsonPath('data.identificador', 'Parcero');
    }

    public function test_el_mensaje_de_cuenta_demasiado_corta_nombra_la_cuenta_del_juego(): void
    {
        $this->actingAs(Usuario::factory()->create())
            ->putJson('/api/v1/panel/cuentas/dota2', ['identificador' => 'ab'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.identificador.0', 'Escribe al menos 3 caracteres para tu Steam ID.');
    }

    public function test_el_catalogo_de_juegos_sigue_diciendo_como_se_llama_la_cuenta_y_su_ejemplo(): void
    {
        $this->getJson('/api/v1/juegos')
            ->assertOk()
            ->assertJsonPath('data.0.cuenta.nombre', 'Riot ID')
            ->assertJsonPath('data.0.cuenta.ejemplo', 'Nombre#LAN');
    }

    public function test_vincular_otra_vez_reemplaza_la_cuenta(): void
    {
        $usuario = Usuario::factory()->create();

        $this->actingAs($usuario)->putJson('/api/v1/panel/cuentas/valorant', ['identificador' => 'Viejo#LAN'])->assertOk();
        $this->actingAs($usuario)->putJson('/api/v1/panel/cuentas/valorant', ['identificador' => 'Nuevo#LAN'])->assertOk();

        $this->assertSame(['Nuevo#LAN'], $usuario->cuentasDeJuego()->pluck('identificador')->all());
    }

    public function test_una_cuenta_de_juego_no_puede_estar_a_nombre_de_dos_personas(): void
    {
        CuentaDeJuego::factory()->paraJuego(Juego::Valorant)->create(['identificador' => 'Unico#LAN']);

        $this->actingAs(Usuario::factory()->create())
            ->putJson('/api/v1/panel/cuentas/valorant', ['identificador' => 'Unico#LAN'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.identificador.0', 'Ese Riot ID ya está vinculado a otra cuenta.');
    }

    public function test_el_perfil_publico_muestra_las_cuentas_pero_no_el_correo_ni_el_nombre(): void
    {
        $usuario = Usuario::factory()->conCuentas(Juego::Valorant)->create(['apodo' => 'Parcero']);

        $this->getJson('/api/v1/jugadores/Parcero')
            ->assertOk()
            ->assertJsonPath('data.apodo', 'Parcero')
            ->assertJsonPath('data.cuentas.0.tipo', 'Riot ID')
            ->assertJsonMissingPath('data.correo')
            ->assertJsonMissingPath('data.nombre');

        $this->assertNotNull($usuario->cuentaPara(Juego::Valorant));
    }
}
