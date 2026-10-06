<?php

namespace Database\Factories;

use App\Enumeraciones\EstadoTorneo;
use App\Enumeraciones\FormatoTorneo;
use App\Enumeraciones\Juego;
use App\Models\Torneo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Por defecto, una copa de Valorant en eliminación simple, con las
 * inscripciones abiertas y sin check-in.
 *
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
        return [
            'juego' => Juego::Valorant,
            'nombre' => 'Copa '.fake()->unique()->city(),
            'formato' => FormatoTorneo::EliminacionSimple,
            'estado' => EstadoTorneo::InscripcionesAbiertas,
            'reglas' => fake()->paragraph(),
            'cupo_maximo_equipos' => 8,
            'mejor_de' => 1,
            'mejor_de_final' => 3,
            'check_in_minutos' => 0,
            'costo_inscripcion_centavos' => 5_000_000,
            'moneda' => 'COP',
            'inscripciones_abren_en' => now()->subWeek(),
            'inscripciones_cierran_en' => now()->addWeeks(2),
            'inicia_en' => now()->addWeeks(3),
        ];
    }

    public function deJuego(Juego $juego): static
    {
        return $this->state([
            'juego' => $juego,
            'formato' => $juego->esBattleRoyale() ? FormatoTorneo::BattleRoyale : FormatoTorneo::EliminacionSimple,
        ]);
    }

    public function conFormato(FormatoTorneo $formato): static
    {
        return $this->state(['formato' => $formato]);
    }

    public function gratuito(): static
    {
        return $this->state(['costo_inscripcion_centavos' => 0]);
    }

    public function borrador(): static
    {
        return $this->state(['estado' => EstadoTorneo::Borrador]);
    }

    public function inscripcionesCerradas(): static
    {
        return $this->state(['estado' => EstadoTorneo::InscripcionesCerradas]);
    }

    public function enCurso(): static
    {
        return $this->state([
            'estado' => EstadoTorneo::EnCurso,
            'inicia_en' => now()->subDay(),
        ]);
    }

    public function finalizado(): static
    {
        return $this->state(['estado' => EstadoTorneo::Finalizado]);
    }

    public function conCheckIn(int $minutos = 30): static
    {
        return $this->state(['check_in_minutos' => $minutos]);
    }
}
