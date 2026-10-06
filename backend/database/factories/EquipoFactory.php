<?php

namespace Database\Factories;

use App\Enumeraciones\Juego;
use App\Models\CuentaDeJuego;
use App\Models\Equipo;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Por defecto, un equipo de Valorant con su capitán como único miembro. Con
 * `completo()` se llena la plantilla titular, cada uno con su cuenta del juego.
 *
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
        $nombre = fake()->randomElement(['Team', 'Clan', 'Squad', 'Gaming', 'Esports']).' '.fake()->unique()->lastName();

        return [
            'juego' => Juego::Valorant,
            'capitan_id' => Usuario::factory(),
            'nombre' => $nombre,
            'abreviatura' => Str::upper(Str::substr(Str::slug($nombre, ''), 0, 4)),
            'color' => fake()->hexColor(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Equipo $equipo): void {
            if (! $equipo->miembros()->whereKey($equipo->capitan_id)->exists()) {
                $equipo->miembros()->attach($equipo->capitan_id, ['es_suplente' => false]);
            }
        });
    }

    public function deJuego(Juego $juego): static
    {
        return $this->state(['juego' => $juego]);
    }

    public function conCapitan(Usuario $capitan): static
    {
        return $this->state(['capitan_id' => $capitan->getKey()]);
    }

    /**
     * Plantilla titular completa y con las cuentas del juego vinculadas,
     * capitán incluido: lista para inscribirse.
     */
    public function completo(): static
    {
        return $this->afterCreating(function (Equipo $equipo): void {
            $capitan = $equipo->capitan;

            if ($capitan->cuentaPara($equipo->juego) === null) {
                CuentaDeJuego::factory()->paraJuego($equipo->juego)->create(['usuario_id' => $capitan->getKey()]);
            }

            $faltan = $equipo->juego->titulares() - $equipo->miembros()->count();

            if ($faltan > 0) {
                $equipo->miembros()->attach(
                    Usuario::factory()->count($faltan)->conCuentas($equipo->juego)->create()->modelKeys(),
                    ['es_suplente' => false],
                );
            }
        });
    }
}
