<?php

namespace Tests\Feature;

use App\Models\Torneo;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * El rasgo TieneSlug: direcciones legibles, únicas y reutilizables tras la baja.
 */
class SlugsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_el_slug_sale_del_nombre_sin_tildes_ni_simbolos(): void
    {
        $torneo = Torneo::factory()->create(['nombre' => 'Copa Año Ñandú: ¡Final!']);

        $this->assertSame('copa-ano-nandu-final', $torneo->slug);
    }

    public function test_los_nombres_repetidos_reciben_un_sufijo_numerico(): void
    {
        $primero = Torneo::factory()->create(['nombre' => 'Copa Repetida']);
        $segundo = Torneo::factory()->create(['nombre' => 'Copa Repetida']);
        $tercero = Torneo::factory()->create(['nombre' => 'Copa Repetida']);

        $this->assertSame('copa-repetida', $primero->slug);
        $this->assertSame('copa-repetida-2', $segundo->slug);
        $this->assertSame('copa-repetida-3', $tercero->slug);
    }

    public function test_un_nombre_sin_letras_recibe_un_slug_aleatorio(): void
    {
        $torneo = Torneo::factory()->create(['nombre' => '!!!!']);

        $this->assertMatchesRegularExpression('/^[a-z0-9]{8}$/', $torneo->slug);
    }

    public function test_un_slug_indicado_a_mano_se_respeta(): void
    {
        $torneo = Torneo::factory()->create(['nombre' => 'Copa Manual', 'slug' => 'mi-direccion']);

        $this->assertSame('mi-direccion', $torneo->slug);
    }

    public function test_al_dar_de_baja_el_slug_se_libera_para_otro_torneo(): void
    {
        $viejo = Torneo::factory()->borrador()->create(['nombre' => 'Copa Libre']);
        $viejo->delete();

        $this->assertSame("copa-libre-baja-{$viejo->id}", Torneo::withTrashed()->findOrFail($viejo->id)->slug);

        $nuevo = Torneo::factory()->create(['nombre' => 'Copa Libre']);

        $this->assertSame('copa-libre', $nuevo->slug);
    }

    public function test_la_ruta_se_resuelve_por_slug_y_no_por_id(): void
    {
        $torneo = Torneo::factory()->create(['nombre' => 'Copa Por Slug']);

        $this->assertSame('slug', $torneo->getRouteKeyName());
        $this->getJson('/api/v1/torneos/copa-por-slug')->assertOk()->assertJsonPath('data.id', $torneo->id);
        $this->getJson("/api/v1/torneos/{$torneo->id}")->assertNotFound();
    }
}
