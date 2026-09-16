<?php

namespace Database\Factories;

use App\Enumeraciones\EstadoPartido;
use App\Models\Equipo;
use App\Models\Partido;
use App\Models\Torneo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Partido>
 */
class PartidoFactory extends Factory
{
    protected $model = Partido::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'torneo_id' => Torneo::factory(),
            'jornada' => 1,
            'es_vuelta' => false,
            'equipo_local_id' => Equipo::factory(),
            'equipo_visitante_id' => Equipo::factory(),
            'programado_para' => now()->addWeek(),
            'sede' => 'Cancha central',
            'estado' => EstadoPartido::Programado,
        ];
    }

    public function entre(Equipo $local, Equipo $visitante): static
    {
        return $this->state([
            'equipo_local_id' => $local->getKey(),
            'equipo_visitante_id' => $visitante->getKey(),
        ]);
    }

    public function delTorneo(Torneo $torneo): static
    {
        return $this->state([
            'torneo_id' => $torneo->getKey(),
            'sede' => $torneo->sede,
        ]);
    }

    public function finalizado(int $golesLocal, int $golesVisitante): static
    {
        return $this->state([
            'estado' => EstadoPartido::Finalizado,
            'goles_local' => $golesLocal,
            'goles_visitante' => $golesVisitante,
            'programado_para' => now()->subWeek(),
            'finalizado_en' => now()->subWeek(),
        ]);
    }

    public function enJornada(int $jornada): static
    {
        return $this->state(['jornada' => $jornada]);
    }
}
