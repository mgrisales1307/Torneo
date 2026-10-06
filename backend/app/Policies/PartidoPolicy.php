<?php

namespace App\Policies;

use App\Models\Equipo;
use App\Models\Partido;
use App\Models\Usuario;

/**
 * Reportan el resultado los capitanes de los dos equipos; lo resuelve la
 * organización.
 */
class PartidoPolicy
{
    public function reportar(Usuario $usuario, Partido $partido): bool
    {
        return $this->equipoQueCapitanea($usuario, $partido) !== null;
    }

    /**
     * Los reportes y las capturas los ven los dos equipos y la organización.
     */
    public function verReportes(Usuario $usuario, Partido $partido): bool
    {
        if ($usuario->perteneceAlStaff()) {
            return true;
        }

        return Equipo::query()
            ->whereIn('id', array_filter([$partido->equipo_a_id, $partido->equipo_b_id]))
            ->delMiembro($usuario)
            ->exists();
    }

    public function resolver(Usuario $usuario, Partido $partido): bool
    {
        return $usuario->perteneceAlStaff();
    }

    /**
     * El equipo de este partido del que el usuario es capitán, si hay alguno.
     */
    public function equipoQueCapitanea(Usuario $usuario, Partido $partido): ?Equipo
    {
        foreach ([$partido->equipoA, $partido->equipoB] as $equipo) {
            if ($equipo?->esCapitan($usuario)) {
                return $equipo;
            }
        }

        return null;
    }
}
