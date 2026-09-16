<?php

namespace App\Enumeraciones;

enum EstadoJugador: string
{
    case Activo = 'activo';
    case Lesionado = 'lesionado';
    case Suspendido = 'suspendido';
    case Inactivo = 'inactivo';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Activo => 'Activo',
            self::Lesionado => 'Lesionado',
            self::Suspendido => 'Suspendido',
            self::Inactivo => 'Inactivo',
        };
    }

    public function puedeJugar(): bool
    {
        return $this === self::Activo;
    }
}
