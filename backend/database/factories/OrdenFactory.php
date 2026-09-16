<?php

namespace Database\Factories;

use App\Enumeraciones\EstadoOrden;
use App\Models\Inscripcion;
use App\Models\Orden;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Orden>
 */
class OrdenFactory extends Factory
{
    protected $model = Orden::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'referencia' => 'JZ-'.Str::upper(Str::random(14)),
            'inscripcion_id' => Inscripcion::factory(),
            'usuario_id' => Usuario::factory()->dueno(),
            'monto_centavos' => 15_000_000,
            'moneda' => 'COP',
            'estado' => EstadoOrden::Pendiente,
            'pasarela' => 'simulada',
            'expira_en' => now()->addHour(),
        ];
    }

    public function deLaInscripcion(Inscripcion $inscripcion): static
    {
        return $this->state([
            'inscripcion_id' => $inscripcion->getKey(),
            'usuario_id' => $inscripcion->equipo->dueno_id,
            'monto_centavos' => $inscripcion->monto_centavos,
            'moneda' => $inscripcion->moneda,
        ]);
    }

    public function pagada(): static
    {
        return $this->state([
            'estado' => EstadoOrden::Pagada,
            'pagada_en' => now(),
        ]);
    }

    public function expirada(): static
    {
        return $this->state(['expira_en' => now()->subMinute()]);
    }
}
