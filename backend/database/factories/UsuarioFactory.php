<?php

namespace Database\Factories;

use App\Enumeraciones\RolUsuario;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Usuario>
 */
class UsuarioFactory extends Factory
{
    protected $model = Usuario::class;

    /**
     * La clave se cifra una sola vez por proceso: bcrypt es lento a propósito,
     * y hacerlo por cada usuario multiplicaría la duración de las pruebas.
     */
    protected static ?string $claveCifrada = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->name(),
            'correo' => fake()->unique()->safeEmail(),
            'telefono' => fake()->numerify('3## ### ####'),
            'email_verified_at' => now(),
            'clave' => static::$claveCifrada ??= Hash::make('password'),
            'rol' => RolUsuario::Espectador,
            'remember_token' => Str::random(10),
        ];
    }

    public function administrador(): static
    {
        return $this->state(['rol' => RolUsuario::Administrador]);
    }

    public function empleado(): static
    {
        return $this->state(['rol' => RolUsuario::Empleado]);
    }

    public function dueno(): static
    {
        return $this->state(['rol' => RolUsuario::Dueno]);
    }

    public function sinVerificar(): static
    {
        return $this->state(['email_verified_at' => null]);
    }
}
