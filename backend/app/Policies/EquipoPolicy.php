<?php

namespace App\Policies;

use App\Models\Equipo;
use App\Models\Usuario;

/**
 * Un equipo lo gestiona su capitán. El staff puede mirar y corregir, pero no
 * inscribir ni pagar por él.
 */
class EquipoPolicy
{
    public function ver(?Usuario $usuario, Equipo $equipo): bool
    {
        return true;
    }

    /**
     * Cualquier cuenta puede fundar un equipo y quedar como su capitán.
     */
    public function crear(Usuario $usuario): bool
    {
        return true;
    }

    /**
     * La ficha completa —con el código de invitación— la ven sus miembros.
     */
    public function verComoMiembro(Usuario $usuario, Equipo $equipo): bool
    {
        return $equipo->tieneMiembro($usuario) || $usuario->perteneceAlStaff();
    }

    public function actualizar(Usuario $usuario, Equipo $equipo): bool
    {
        return ! $equipo->es_individual && ($equipo->esCapitan($usuario) || $usuario->perteneceAlStaff());
    }

    public function eliminar(Usuario $usuario, Equipo $equipo): bool
    {
        return ! $equipo->es_individual && ($equipo->esCapitan($usuario) || $usuario->esAdministrador());
    }

    /**
     * Cambiar suplentes, ceder la capitanía, sacar a alguien o renovar el
     * código.
     */
    public function gestionarPlantilla(Usuario $usuario, Equipo $equipo): bool
    {
        return ! $equipo->es_individual && $equipo->esCapitan($usuario);
    }

    /**
     * Inscribir y pagar es solo del capitán.
     */
    public function inscribir(Usuario $usuario, Equipo $equipo): bool
    {
        return $equipo->esCapitan($usuario);
    }
}
