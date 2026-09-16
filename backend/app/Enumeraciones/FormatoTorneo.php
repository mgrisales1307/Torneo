<?php

namespace App\Enumeraciones;

/**
 * Formato de competencia. Hoy el generador de calendario resuelve los dos
 * formatos de liga; la eliminación directa está reservada para la siguiente
 * etapa y por eso no se ofrece todavía en la validación de creación.
 */
enum FormatoTorneo: string
{
    case LigaIda = 'liga_ida';
    case LigaIdaVuelta = 'liga_ida_vuelta';
    case EliminacionDirecta = 'eliminacion_directa';

    public function etiqueta(): string
    {
        return match ($this) {
            self::LigaIda => 'Liga (todos contra todos)',
            self::LigaIdaVuelta => 'Liga (ida y vuelta)',
            self::EliminacionDirecta => 'Eliminación directa',
        };
    }

    public function esLiga(): bool
    {
        return $this === self::LigaIda || $this === self::LigaIdaVuelta;
    }

    public function tieneVuelta(): bool
    {
        return $this === self::LigaIdaVuelta;
    }

    /**
     * Formatos que el generador de calendario ya sabe construir.
     *
     * @return array<int, string>
     */
    public static function valoresImplementados(): array
    {
        return [self::LigaIda->value, self::LigaIdaVuelta->value];
    }
}
