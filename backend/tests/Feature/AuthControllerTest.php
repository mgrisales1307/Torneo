<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_valid_credentials_authenticate_user_and_return_profile(): void
    {
        $user = User::factory()->create(['email' => 'capitan@example.com']);

        $response = $this->withHeader('Origin', 'http://localhost:5173')->postJson('/api/login', [
            'email' => 'capitan@example.com',
            'password' => 'password',
            'remember' => true,
        ]);

        $response->assertOk()->assertJsonPath('user.id', $user->id)->assertJsonPath('user.role', 'user');
        $this->assertAuthenticatedAs($user);
    }

    public function test_returns_422_for_invalid_credentials(): void
    {
        User::factory()->create(['email' => 'capitan@example.com']);

        $this->withHeader('Origin', 'http://localhost:5173')->postJson('/api/login', [
            'email' => 'capitan@example.com',
            'password' => 'incorrecta',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_returns_401_when_profile_is_requested_without_a_session(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_authenticated_user_can_recover_profile_and_logout(): void
    {
        $user = User::factory()->create();

        $this->withHeader('Origin', 'http://localhost:5173')->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        $this->withHeader('Origin', 'http://localhost:5173')->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.email', $user->email);

        $this->withHeader('Origin', 'http://localhost:5173')->postJson('/api/logout')->assertOk();
        $this->assertGuest('web');
    }
}
