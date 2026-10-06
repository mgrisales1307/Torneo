<?php

namespace Database\Factories;

use App\Enumeraciones\EstadoInscripcion;
use App\Models\Equipo;
use App\Models\Inscripcion;
use App\Models\Torneo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inscripcion>
 */
class InscripcionFactory extends Factory
{
    protected $model = Inscripcion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'torneo_id' => Torneo::factory(),
            'equipo_id' => Equipo::factory(),
            'estado' => EstadoInscripcion::PendientePago,
            'monto_centavos' => 5_000_000,
            'moneda' => 'COP',
        ];
    }

    public function pendienteDeRevision(): static
    {
        return $this->state([
            'estado' => EstadoInscripcion::PendienteRevision,
            'pagada_en' => now(),
        ]);
    }

    public function aprobada(): static
    {
        return $this->state([
            'estado' => EstadoInscripcion::Aprobada,
            'pagada_en' => now(),
            'revisado_en' => now(),
        ]);
    }

    public function rechazada(): static
    {
        return $this->state([
            'estado' => EstadoInscripcion::Rechazada,
            'revisado_en' => now(),
            'notas_revision' => 'Documentación incompleta.',
        ]);
    }

    public function conCheckIn(): static
    {
        return $this->state(['check_in_en' => now()]);
    }

    public function gratuita(): static
    {
        return $this->state(['monto_centavos' => 0]);
    }
}
