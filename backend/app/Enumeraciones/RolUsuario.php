<?php

namespace App\Enumeraciones;

/**
 * Roles del sistema. El rol vive en la columna `usuarios.rol`.
 *
 * - Jugador: cualquiera que se registre. Crea equipos, se une a otros con un
 *   código e inscribe a los que capitanea.
 * - Empleado: organiza la competencia (torneos, inscripciones, resultados).
 * - Administrador: todo lo anterior más la gestión de usuarios.
 *
 * Capitán no es un rol de la cuenta: es un cargo dentro de cada equipo.
 */
enum RolUsuario: string
{
    case Administrador = 'admin';
    case Empleado = 'empleado';
    case Jugador = 'jugador';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Administrador => 'Administración',
            self::Empleado => 'Organización',
            self::Jugador => 'Jugador',
        };
    }

    /**
     * Roles que administran la competencia.
     *
     * @return array<int, string>
     */
    public static function valoresDeStaff(): array
    {
        return [self::Administrador->value, self::Empleado->value];
    }

    /**
     * @return array<int, string>
     */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
