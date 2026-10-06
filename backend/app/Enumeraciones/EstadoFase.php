<?php

namespace App\Enumeraciones;

enum EstadoFase: string
{
    case EnCurso = 'en_curso';
    case Finalizada = 'finalizada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::EnCurso => 'En curso',
            self::Finalizada => 'Finalizada',
        };
    }
}
