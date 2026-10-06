<?php

namespace App\Policies;

use App\Models\Inscripcion;
use App\Models\Usuario;

class InscripcionPolicy
{
    public function ver(Usuario $usuario, Inscripcion $inscripcion): bool
    {
        return $this->esSuCapitan($usuario, $inscripcion) || $usuario->perteneceAlStaff();
    }

    /**
     * Pagar la inscripción es del capitán del equipo, de nadie más.
     */
    public function pagar(Usuario $usuario, Inscripcion $inscripcion): bool
    {
        return $this->esSuCapitan($usuario, $inscripcion);
    }

    public function retirar(Usuario $usuario, Inscripcion $inscripcion): bool
    {
        return $this->esSuCapitan($usuario, $inscripcion);
    }

    public function hacerCheckIn(Usuario $usuario, Inscripcion $inscripcion): bool
    {
        return $this->esSuCapitan($usuario, $inscripcion);
    }

    public function revisar(Usuario $usuario, Inscripcion $inscripcion): bool
    {
        return $usuario->perteneceAlStaff();
    }

    private function esSuCapitan(Usuario $usuario, Inscripcion $inscripcion): bool
    {
        return $inscripcion->equipo->esCapitan($usuario);
    }
}
