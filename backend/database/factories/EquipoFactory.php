<?php

namespace Database\Factories;

use App\Models\Equipo;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Equipo>
 */
class EquipoFactory extends Factory
{
    protected $model = Equipo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nombre = fake()->randomElement([
            'Atlético', 'Deportivo', 'Real', 'Unión', 'Independiente', 'Racing', 'Junior', 'Sporting',
        ]).' '.fake()->unique()->lastName();

        return [
            'dueno_id' => Usuario::factory()->dueno(),
            'nombre' => $nombre,
            'abreviatura' => Str::upper(Str::substr(Str::slug($nombre), 0, 3)),
            'ciudad' => fake()->city(),
            'estadio' => 'Cancha '.fake()->lastName(),
            'fundado_en' => fake()->dateTimeBetween('-30 years', '-1 year'),
            'descripcion' => fake()->sentence(),
            'color_primario' => fake()->hexColor(),
            'color_secundario' => fake()->hexColor(),
            'activo' => true,
        ];
    }

    public function delDueno(Usuario $dueno): static
    {
        return $this->state(['dueno_id' => $dueno->getKey()]);
    }
}
