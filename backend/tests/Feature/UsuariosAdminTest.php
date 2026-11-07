<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class UsuariosAdminTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_el_listado_va_ordenado_por_apodo_y_trae_el_rol(): void
    {
        $administrador = Usuario::factory()->administrador()->create(['apodo' => 'Zeta']);
        Usuario::factory()->create(['apodo' => 'Alfa']);
        Usuario::factory()->empleado()->create(['apodo' => 'Beta']);

        $respuesta = $this->actingAs($administrador)->getJson('/api/v1/admin/usuarios')->assertOk();

        $this->assertSame(['Alfa', 'Beta', 'Zeta'], $respuesta->json('data.*.apodo'));
        $this->assertSame('empleado', $respuesta->json('data.1.rol.valor'));
    }

    public function test_se_filtra_por_rol(): void
    {
        $administrador = Usuario::factory()->administrador()->create();
        Usuario::factory()->count(3)->create();
        Usuario::factory()->empleado()->create();

        $this->actingAs($administrador)
            ->getJson('/api/v1/admin/usuarios?rol=jugador')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $this->getJson('/api/v1/admin/usuarios?rol=empleado')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/admin/usuarios?rol=admin')->assertJsonCount(1, 'data');
    }

    public function test_un_rol_inexistente_se_ignora_y_no_rompe_el_listado(): void
    {
        $administrador = Usuario::factory()->administrador()->create();
        Usuario::factory()->count(2)->create();

        $this->actingAs($administrador)
            ->getJson('/api/v1/admin/usuarios?rol=superhombre')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_se_busca_por_apodo_nombre_o_correo(): void
    {
        $administrador = Usuario::factory()->administrador()->create([
            'apodo' => 'Admin01',
            'nombre' => 'Administrador Prueba',
            'correo' => 'admin@prueba.test',
        ]);
        Usuario::factory()->create(['apodo' => 'CondorAndino', 'nombre' => 'Laura Gómez', 'correo' => 'laura@ejemplo.test']);
        Usuario::factory()->create(['apodo' => 'Otro', 'nombre' => 'Pedro Ruiz', 'correo' => 'pedro@ejemplo.test']);

        $this->actingAs($administrador);

        $this->getJson('/api/v1/admin/usuarios?buscar=Condor')->assertJsonCount(1, 'data')->assertJsonPath('data.0.apodo', 'CondorAndino');
        $this->getJson('/api/v1/admin/usuarios?buscar=Pedro')->assertJsonCount(1, 'data')->assertJsonPath('data.0.apodo', 'Otro');
        $this->getJson('/api/v1/admin/usuarios?buscar=laura@ejemplo')->assertJsonCount(1, 'data')->assertJsonPath('data.0.apodo', 'CondorAndino');
        $this->getJson('/api/v1/admin/usuarios?buscar=nadie-se-llama-asi')->assertJsonCount(0, 'data');
    }

    public function test_la_respuesta_nunca_incluye_la_clave(): void
    {
        $administrador = Usuario::factory()->administrador()->create();
        Usuario::factory()->create();

        $respuesta = $this->actingAs($administrador)->getJson('/api/v1/admin/usuarios')->assertOk();

        $respuesta->assertJsonMissingPath('data.0.clave');
        $respuesta->assertJsonMissingPath('data.0.remember_token');
        $this->assertStringNotContainsString('$2y$', $respuesta->getContent());
    }

    public function test_el_listado_se_pagina_de_a_veinticinco(): void
    {
        $administrador = Usuario::factory()->administrador()->create();
        Usuario::factory()->count(30)->create();

        $this->actingAs($administrador)
            ->getJson('/api/v1/admin/usuarios')
            ->assertOk()
            ->assertJsonCount(25, 'data')
            ->assertJsonPath('meta.total', 31);

        $this->getJson('/api/v1/admin/usuarios?page=2')->assertJsonCount(6, 'data');
    }
}
