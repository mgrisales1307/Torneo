<?php

namespace App\Policies;

use App\Models\Torneo;
use App\Models\Usuario;

/**
 * Los torneos los ve todo el mundo; los administra el staff.
 */
class TorneoPolicy
{
    public function ver(?Usuario $usuario, Torneo $torneo): bool
    {
        if ($torneo->estado->esVisibleAlPublico()) {
            return true;
        }

        // Un torneo en borrador solo lo ve quien lo está armando.
        return $usuario?->perteneceAlStaff() ?? false;
    }

    public function administrar(Usuario $usuario): bool
    {
        return $usuario->perteneceAlStaff();
    }

    public function crear(Usuario $usuario): bool
    {
        return $usuario->perteneceAlStaff();
    }

    public function actualizar(Usuario $usuario, Torneo $torneo): bool
    {
        return $usuario->perteneceAlStaff();
    }

    /**
     * Borrar un torneo se lleva por delante inscripciones y cuadro, así que
     * queda reservado a la administración y solo mientras no haya empezado.
     */
    public function eliminar(Usuario $usuario, Torneo $torneo): bool
    {
        return $usuario->esAdministrador() && ! $torneo->estado->haEmpezado();
    }

    public function iniciar(Usuario $usuario, Torneo $torneo): bool
    {
        return $usuario->perteneceAlStaff();
    }
}
