<?php

namespace Database\Factories;

use App\Enumeraciones\Juego;
use App\Models\CuentaDeJuego;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CuentaDeJuego>
 */
class CuentaDeJuegoFactory extends Factory
{
    protected $model = CuentaDeJuego::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'usuario_id' => Usuario::factory(),
            'juego' => Juego::Valorant,
            'identificador' => self::identificadorPara(Juego::Valorant),
        ];
    }

    public function paraJuego(Juego $juego): static
    {
        return $this->state([
            'juego' => $juego,
            'identificador' => self::identificadorPara($juego),
        ]);
    }

    /**
     * Un identificador válido para el formato de cada juego.
     */
    public static function identificadorPara(Juego $juego): string
    {
        return match ($juego) {
            Juego::Valorant, Juego::LeagueOfLegends => fake()->unique()->bothify('Jugador?????').'#'.fake()->randomElement(['LAN', 'COL', 'LAS']),
            Juego::Dota2 => '7656119'.fake()->unique()->numerify('##########'),
            Juego::Pubg => fake()->unique()->bothify('Pubg_?????##'),
            Juego::FreeFire => fake()->unique()->numerify('#########'),
        };
    }
}
