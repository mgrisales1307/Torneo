<?php

namespace App\Enumeraciones;

/**
 * Tipo de cada fase del torneo. Un torneo de grupos tiene dos fases (grupos y
 * eliminación simple); los demás formatos, una sola.
 */
enum TipoDeFase: string
{
    case EliminacionSimple = 'eliminacion_simple';
    case EliminacionDoble = 'eliminacion_doble';
    case Grupos = 'grupos';
    case Suizo = 'suizo';
    case BattleRoyale = 'battle_royale';

    public function etiqueta(): string
    {
        return match ($this) {
            self::EliminacionSimple => 'Eliminación simple',
            self::EliminacionDoble => 'Eliminación doble',
            self::Grupos => 'Fase de grupos',
            self::Suizo => 'Sistema suizo',
            self::BattleRoyale => 'Partidas',
        };
    }

    public function esEliminacion(): bool
    {
        return in_array($this, [self::EliminacionSimple, self::EliminacionDoble], true);
    }

    /**
     * Las fases que se ordenan con una tabla en vez de con un cuadro.
     */
    public function tieneTabla(): bool
    {
        return ! $this->esEliminacion();
    }
}
