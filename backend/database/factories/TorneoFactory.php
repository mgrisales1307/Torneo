<?php

namespace Database\Factories;

use App\Enumeraciones\EstadoTorneo;
use App\Enumeraciones\FormatoTorneo;
use App\Models\Torneo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Torneo>
 */
class TorneoFactory extends Factory
{
    protected $model = Torneo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nombre = 'Copa '.fake()->unique()->city();

        return [
            'nombre' => $nombre,
            'temporada' => (string) now()->year,
            'categoria' => fake()->randomElement(['Libre', 'Sub-20', 'Sub-17', 'Veteranos']),
            'descripcion' => fake()->paragraph(),
            'formato' => FormatoTorneo::LigaIda,
            'estado' => EstadoTorneo::InscripcionesAbiertas,
            'ciudad' => fake()->city(),
            'sede' => 'Cancha '.fake()->numberBetween(1, 9),
            'cupo_maximo_equipos' => 8,
            'minimo_jugadores_por_equipo' => 7,
            'maximo_jugadores_por_equipo' => 20,
            'costo_inscripcion_centavos' => 15_000_000,
            'moneda' => 'COP',
            'inscripciones_abren_en' => now()->subWeek(),
            'inscripciones_cierran_en' => now()->addWeeks(2),
            'inicia_en' => now()->addWeeks(3),
        ];
    }

    public function gratuito(): static
    {
        return $this->state(['costo_inscripcion_centavos' => 0]);
    }

    public function borrador(): static
    {
        return $this->state(['estado' => EstadoTorneo::Borrador]);
    }

    public function enCurso(): static
    {
        return $this->state([
            'estado' => EstadoTorneo::EnCurso,
            'inicia_en' => now()->subWeek(),
        ]);
    }

    public function finalizado(): static
    {
        return $this->state(['estado' => EstadoTorneo::Finalizado]);
    }

    public function inscripcionesCerradas(): static
    {
        return $this->state(['estado' => EstadoTorneo::InscripcionesCerradas]);
    }

    public function conIdaYVuelta(): static
    {
        return $this->state(['formato' => FormatoTorneo::LigaIdaVuelta]);
    }
}
