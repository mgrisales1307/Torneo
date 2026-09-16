<?php

namespace App\Policies;

use App\Models\Orden;
use App\Models\Usuario;

class OrdenPolicy
{
    public function ver(Usuario $usuario, Orden $orden): bool
    {
        return $usuario->getKey() === $orden->usuario_id || $usuario->perteneceAlStaff();
    }

    public function pagar(Usuario $usuario, Orden $orden): bool
    {
        return $usuario->getKey() === $orden->usuario_id;
    }
}
