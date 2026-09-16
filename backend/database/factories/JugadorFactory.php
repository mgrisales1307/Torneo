<?php

namespace Database\Factories;

use App\Enumeraciones\EstadoJugador;
use App\Enumeraciones\PosicionJugador;
use App\Models\Equipo;
use App\Models\Jugador;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Jugador>
 */
class JugadorFactory extends Factory
{
    protected $model = Jugador::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'equipo_id' => Equipo::factory(),
            'nombres' => fake()->firstName('male'),
            'apellidos' => fake()->lastName().' '.fake()->lastName(),
            'documento_identidad' => (string) fake()->unique()->numberBetween(1_000_000_00, 1_999_999_99),
            'fecha_nacimiento' => fake()->dateTimeBetween('-38 years', '-16 years'),
            'posicion' => fake()->randomElement(PosicionJugador::cases()),
            'numero_camiseta' => null,
            'estado' => EstadoJugador::Activo,
            'es_capitan' => false,
            'telefono' => fake()->numerify('3## ### ####'),
        ];
    }

    public function conDorsal(int $numero): static
    {
        return $this->state(['numero_camiseta' => $numero]);
    }

    public function capitan(): static
    {
        return $this->state(['es_capitan' => true]);
    }

    public function lesionado(): static
    {
        return $this->state(['estado' => EstadoJugador::Lesionado]);
    }

    public function delEquipo(Equipo $equipo): static
    {
        return $this->state(['equipo_id' => $equipo->getKey()]);
    }
}
