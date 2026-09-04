<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminSeeder;
use Database\Seeders\EmpleadoSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class RolesAccesoTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_can_list_users(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->getJson('/api/usuarios')->assertOk();
    }

    public function test_empleado_can_list_users(): void
    {
        $empleado = User::factory()->create(['role' => 'empleado']);

        $this->actingAs($empleado)->getJson('/api/usuarios')->assertOk();
    }

    public function test_participant_cannot_list_users(): void
    {
        $participante = User::factory()->create(['role' => 'user']);

        $this->actingAs($participante)
            ->getJson('/api/usuarios')
            ->assertForbidden()
            ->assertJsonPath('message', 'No tienes permiso para realizar esta acción.');
    }

    public function test_seeder_creates_the_admin_account(): void
    {
        $this->seed(AdminSeeder::class);

        $admin = User::where('email', 'admin@juegoszone.com')->firstOrFail();

        $this->assertSame('admin', $admin->role);
        $this->actingAs($admin)->getJson('/api/usuarios')->assertOk();
    }

    public function test_seeder_creates_the_empleado_account(): void
    {
        $this->seed(EmpleadoSeeder::class);

        $empleado = User::where('email', 'empleado@juegoszone.com')->firstOrFail();

        $this->assertSame('empleado', $empleado->role);
        $this->actingAs($empleado)->getJson('/api/usuarios')->assertOk();
    }

    public function test_seeders_do_not_duplicate_accounts_when_run_twice(): void
    {
        $this->seed(AdminSeeder::class);
        $this->seed(AdminSeeder::class);
        $this->seed(EmpleadoSeeder::class);
        $this->seed(EmpleadoSeeder::class);

        $this->assertSame(1, User::where('email', 'admin@juegoszone.com')->count());
        $this->assertSame(1, User::where('email', 'empleado@juegoszone.com')->count());
    }
}
