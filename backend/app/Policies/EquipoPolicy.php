<?php

namespace App\Policies;

use App\Models\Equipo;
use App\Models\Usuario;

/**
 * Un equipo lo gestiona su dueño. El staff puede mirar y corregir, pero no
 * pagar por él.
 */
class EquipoPolicy
{
    public function ver(?Usuario $usuario, Equipo $equipo): bool
    {
        return true;
    }

    public function crear(Usuario $usuario): bool
    {
        return $usuario->puedeTenerEquipos();
    }

    public function actualizar(Usuario $usuario, Equipo $equipo): bool
    {
        return $this->esSuDueno($usuario, $equipo) || $usuario->perteneceAlStaff();
    }

    public function eliminar(Usuario $usuario, Equipo $equipo): bool
    {
        if (! $this->esSuDueno($usuario, $equipo) && ! $usuario->esAdministrador()) {
            return false;
        }

        // Un equipo con inscripciones vivas no se borra: primero se retira del
        // torneo, para no dejar un calendario apuntando al vacío.
        return ! $equipo->inscripciones()->pagadas()->exists();
    }

    public function gestionarPlantilla(Usuario $usuario, Equipo $equipo): bool
    {
        return $this->esSuDueno($usuario, $equipo) || $usuario->perteneceAlStaff();
    }

    /**
     * Inscribir y pagar es solo del dueño: es su dinero.
     */
    public function inscribir(Usuario $usuario, Equipo $equipo): bool
    {
        return $this->esSuDueno($usuario, $equipo);
    }

    private function esSuDueno(Usuario $usuario, Equipo $equipo): bool
    {
        return $usuario->getKey() === $equipo->dueno_id;
    }
}
