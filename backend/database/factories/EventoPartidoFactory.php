<?php

namespace Database\Factories;

use App\Enumeraciones\TipoEventoPartido;
use App\Models\EventoPartido;
use App\Models\Jugador;
use App\Models\Partido;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventoPartido>
 */
class EventoPartidoFactory extends Factory
{
    protected $model = EventoPartido::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $jugador = Jugador::factory();

        return [
            'partido_id' => Partido::factory(),
            'jugador_id' => $jugador,
            'equipo_id' => fn (array $atributos): int => Jugador::query()
                ->whereKey($atributos['jugador_id'])
                ->value('equipo_id'),
            'tipo' => TipoEventoPartido::Gol,
            'minuto' => fake()->numberBetween(1, 90),
        ];
    }

    public function deJugador(Jugador $jugador): static
    {
        return $this->state([
            'jugador_id' => $jugador->getKey(),
            'equipo_id' => $jugador->equipo_id,
        ]);
    }

    public function tipo(TipoEventoPartido $tipo): static
    {
        return $this->state(['tipo' => $tipo]);
    }
}
