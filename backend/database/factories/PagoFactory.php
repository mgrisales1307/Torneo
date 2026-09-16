<?php

namespace Database\Factories;

use App\Enumeraciones\EstadoPago;
use App\Models\Orden;
use App\Models\Pago;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Pago>
 */
class PagoFactory extends Factory
{
    protected $model = Pago::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'orden_id' => Orden::factory(),
            'pasarela' => 'simulada',
            'referencia_externa' => 'SIM-'.Str::upper(Str::random(18)),
            'estado' => EstadoPago::Aprobado,
            'monto_centavos' => 15_000_000,
            'moneda' => 'COP',
            'metodo' => 'tarjeta_de_prueba',
            'procesado_en' => now(),
        ];
    }

    public function rechazado(): static
    {
        return $this->state(['estado' => EstadoPago::Rechazado]);
    }
}
