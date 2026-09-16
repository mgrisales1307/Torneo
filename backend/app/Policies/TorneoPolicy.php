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
     * Borrar un torneo se lleva por delante inscripciones y calendario, así
     * que queda reservado a la administración y solo mientras no haya empezado.
     */
    public function eliminar(Usuario $usuario, Torneo $torneo): bool
    {
        return $usuario->esAdministrador() && ! $torneo->partidos()->exists();
    }

    public function gestionarCalendario(Usuario $usuario, Torneo $torneo): bool
    {
        return $usuario->perteneceAlStaff();
    }

    /**
     * Solo decide si esta persona puede inscribir equipos. Si el torneo admite
     * inscripciones ahora mismo —fechas, estado y cupo— lo resuelve
     * GestorDeInscripciones, que explica en el mensaje cuál es el problema.
     */
    public function inscribirEquipo(Usuario $usuario, Torneo $torneo): bool
    {
        return $usuario->puedeTenerEquipos();
    }
}
