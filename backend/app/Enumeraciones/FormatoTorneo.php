<?php

namespace App\Enumeraciones;

/**
 * Cómo se compite en un torneo. Los cuatro primeros son para juegos de duelo;
 * el último, para battle royale.
 */
enum FormatoTorneo: string
{
    case EliminacionSimple = 'eliminacion_simple';
    case EliminacionDoble = 'eliminacion_doble';
    case GruposYEliminacion = 'grupos_y_eliminacion';
    case Suizo = 'suizo';
    case BattleRoyale = 'battle_royale';

    public function etiqueta(): string
    {
        return match ($this) {
            self::EliminacionSimple => 'Eliminación simple',
            self::EliminacionDoble => 'Eliminación doble',
            self::GruposYEliminacion => 'Grupos y eliminación',
            self::Suizo => 'Sistema suizo',
            self::BattleRoyale => 'Battle royale por puntos',
        };
    }

    public function esBattleRoyale(): bool
    {
        return $this === self::BattleRoyale;
    }

    /**
     * Mínimo de equipos para que el formato tenga sentido. Con menos de cuatro,
     * una llave inferior o un suizo quedan vacíos.
     */
    public function minimoDeEquipos(): int
    {
        return match ($this) {
            self::EliminacionSimple, self::BattleRoyale => 2,
            default => 4,
        };
    }

    /**
     * @return array<int, string>
     */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
