<?php

namespace App\Enumeraciones;

/**
 * Roles del sistema. El rol vive en la columna `usuarios.rol`.
 *
 * - Espectador: cualquiera que se registre para seguir el torneo.
 * - Dueno: registra equipos, paga la inscripción y gestiona jugadores.
 * - Empleado: organiza la competencia (calendario, resultados, inscripciones).
 * - Administrador: todo lo anterior más la gestión de usuarios.
 */
enum RolUsuario: string
{
    case Administrador = 'admin';
    case Empleado = 'empleado';
    case Dueno = 'dueno';
    case Espectador = 'espectador';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Administrador => 'Administración',
            self::Empleado => 'Organización',
            self::Dueno => 'Dueño de equipo',
            self::Espectador => 'Espectador',
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
