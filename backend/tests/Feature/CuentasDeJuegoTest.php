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
     * @return array<string, array{string, string, bool}>
     */
    public static function cuentas(): array
    {
        return [
            'Riot ID válido' => ['valorant', 'Parcero#LAN', true],
            'Riot ID sin etiqueta' => ['valorant', 'Parcero', false],
            'Riot ID en LoL' => ['lol', 'La Mona#COL1', true],
            'Steam ID de 17 cifras' => ['dota2', '76561198012345678', true],
            'Steam ID corto' => ['dota2', '12345', false],
            'nombre de PUBG' => ['pubg', 'El_Paisa-99', true],
            'nombre de PUBG con espacios' => ['pubg', 'El Paisa', false],
            'UID de Free Fire' => ['freefire', '123456789', true],
            'UID con letras' => ['freefire', 'abc123456', false],
        ];
    }

    #[DataProvider('cuentas')]
    public function test_cada_juego_valida_el_formato_de_su_cuenta(string $juego, string $identificador, bool $valida): void
    {
        $respuesta = $this->actingAs(Usuario::factory()->create())
            ->putJson("/api/v1/panel/cuentas/$juego", ['identificador' => $identificador]);

        $valida
            ? $respuesta->assertOk()->assertJsonPath('data.identificador', $identificador)
            : $respuesta->assertUnprocessable()->assertJsonValidationErrors('identificador');
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
