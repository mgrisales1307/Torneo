<?php

namespace App\Policies;

use App\Models\Inscripcion;
use App\Models\Usuario;

class InscripcionPolicy
{
    public function ver(Usuario $usuario, Inscripcion $inscripcion): bool
    {
        return $this->esSuya($usuario, $inscripcion) || $usuario->perteneceAlStaff();
    }

    /**
     * Pagar la inscripción es del dueño del equipo, de nadie más.
     */
    public function pagar(Usuario $usuario, Inscripcion $inscripcion): bool
    {
        return $this->esSuya($usuario, $inscripcion);
    }

    public function retirar(Usuario $usuario, Inscripcion $inscripcion): bool
    {
        return $this->esSuya($usuario, $inscripcion);
    }

    public function revisar(Usuario $usuario, Inscripcion $inscripcion): bool
    {
        return $usuario->perteneceAlStaff();
    }

    private function esSuya(Usuario $usuario, Inscripcion $inscripcion): bool
    {
        return $usuario->getKey() === $inscripcion->equipo->dueno_id;
    }
}
