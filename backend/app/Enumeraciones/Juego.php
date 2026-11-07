<?php

namespace App\Enumeraciones;

/**
 * Los cinco juegos de la plataforma y todo lo que cambia de uno a otro: cuántos
 * juegan por equipo, con qué cuenta se identifica cada jugador y cómo se puntúa
 * una partida de battle royale.
 *
 * Es un enum y no una tabla porque cada juego trae reglas propias que viven en
 * código (el formato de su cuenta, su tabla de puntos). Sumar un juego es sumar
 * un caso aquí.
 */
enum Juego: string
{
    case Valorant = 'valorant';
    case LeagueOfLegends = 'lol';
    case Dota2 = 'dota2';
    case Pubg = 'pubg';
    case FreeFire = 'freefire';

    public function nombre(): string
    {
        return match ($this) {
            self::Valorant => 'Valorant',
            self::LeagueOfLegends => 'League of Legends',
            self::Dota2 => 'Dota 2',
            self::Pubg => 'PUBG: Battlegrounds',
            self::FreeFire => 'Free Fire',
        };
    }

    public function abreviatura(): string
    {
        return match ($this) {
            self::Valorant => 'VAL',
            self::LeagueOfLegends => 'LOL',
            self::Dota2 => 'DOTA',
            self::Pubg => 'PUBG',
            self::FreeFire => 'FF',
        };
    }

    public function esBattleRoyale(): bool
    {
        return in_array($this, [self::Pubg, self::FreeFire], true);
    }

    public function modalidad(): string
    {
        return $this->esBattleRoyale()
            ? 'Battle royale · escuadras de '.$this->titulares()
            : 'Duelo '.$this->titulares().'v'.$this->titulares();
    }

    public function titulares(): int
    {
        return $this->esBattleRoyale() ? 4 : 5;
    }

    public function suplentesMaximos(): int
    {
        return $this->esBattleRoyale() ? 1 : 2;
    }

    public function maximoDeMiembros(): int
    {
        return $this->titulares() + $this->suplentesMaximos();
    }

    public function plataforma(): string
    {
        return match ($this) {
            self::Pubg => 'PC y consola',
            self::FreeFire => 'Móvil',
            default => 'PC',
        };
    }

    /**
     * Cómo se llama la cuenta con la que se identifica a un jugador.
     */
    public function nombreDeCuenta(): string
    {
        return match ($this) {
            self::Valorant, self::LeagueOfLegends => 'Riot ID',
            self::Dota2 => 'Steam ID',
            self::Pubg => 'Nombre en PUBG',
            self::FreeFire => 'UID de Free Fire',
        };
    }

    /**
     * Texto de ejemplo que se muestra en el campo. La vinculación es simulada:
     * no se valida el formato ni se verifica la cuenta con el juego.
     */
    public function ejemploDeCuenta(): string
    {
        return match ($this) {
            self::Valorant, self::LeagueOfLegends => 'Nombre#LAN',
            self::Dota2 => '76561198012345678',
            self::Pubg => 'Nombre_PUBG',
            self::FreeFire => '123456789',
        };
    }

    /**
     * Equipos que caben en una misma partida. En los duelos son dos; en battle
     * royale, lo que admite una sala personalizada.
     */
    public function equiposPorPartida(): int
    {
        return match ($this) {
            self::Pubg => 16,
            self::FreeFire => 12,
            default => 2,
        };
    }

    /**
     * Puntos por puesto en una partida de battle royale. Son las tablas de los
     * circuitos oficiales: la de PUBG Global Series y la de Free Fire World
     * Series. Cada baja suma además un punto.
     */
    public function puntosPorPosicion(int $posicion): int
    {
        $tabla = match ($this) {
            self::Pubg => [1 => 10, 2 => 6, 3 => 5, 4 => 4, 5 => 3, 6 => 2, 7 => 1, 8 => 1],
            self::FreeFire => [1 => 12, 2 => 9, 3 => 8, 4 => 7, 5 => 6, 6 => 5, 7 => 4, 8 => 3, 9 => 2, 10 => 1],
            default => [],
        };

        return $tabla[$posicion] ?? 0;
    }

    public function puntosPorBaja(): int
    {
        return 1;
    }

    /**
     * @return list<FormatoTorneo>
     */
    public function formatosPermitidos(): array
    {
        return array_values(array_filter(
            FormatoTorneo::cases(),
            fn (FormatoTorneo $formato): bool => $formato->esBattleRoyale() === $this->esBattleRoyale(),
        ));
    }

    /**
     * La ficha del juego tal como la consume el frontend.
     *
     * @return array<string, mixed>
     */
    public function comoArreglo(): array
    {
        return [
            'valor' => $this->value,
            'nombre' => $this->nombre(),
            'abreviatura' => $this->abreviatura(),
            'modalidad' => $this->modalidad(),
            'es_battle_royale' => $this->esBattleRoyale(),
            'titulares' => $this->titulares(),
            'suplentes_maximos' => $this->suplentesMaximos(),
            'plataforma' => $this->plataforma(),
            'cuenta' => [
                'nombre' => $this->nombreDeCuenta(),
                'ejemplo' => $this->ejemploDeCuenta(),
            ],
            'equipos_por_partida' => $this->equiposPorPartida(),
            'formatos' => array_map(
                fn (FormatoTorneo $formato): array => ['valor' => $formato->value, 'etiqueta' => $formato->etiqueta()],
                $this->formatosPermitidos(),
            ),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
