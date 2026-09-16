<?php

namespace App\Policies;

use App\Models\Jugador;
use App\Models\Usuario;

class JugadorPolicy
{
    public function ver(?Usuario $usuario, Jugador $jugador): bool
    {
        return true;
    }

    public function actualizar(Usuario $usuario, Jugador $jugador): bool
    {
        return $this->puedeGestionarSuEquipo($usuario, $jugador);
    }

    public function eliminar(Usuario $usuario, Jugador $jugador): bool
    {
        return $this->puedeGestionarSuEquipo($usuario, $jugador);
    }

    private function puedeGestionarSuEquipo(Usuario $usuario, Jugador $jugador): bool
    {
        return $usuario->getKey() === $jugador->equipo->dueno_id || $usuario->perteneceAlStaff();
    }
}
