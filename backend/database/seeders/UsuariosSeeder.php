<?php

namespace Database\Seeders;

use App\Enumeraciones\RolUsuario;
use App\Models\Usuario;
use Illuminate\Database\Seeder;

/**
 * Cuentas de desarrollo, una por rol.
 *
 * Son EXCLUSIVAMENTE para trabajar en local: están documentadas en el README y
 * cualquiera que clone el repositorio las conoce. Se crean con updateOrCreate,
 * así que volver a sembrar no duplica nada ni pisa los datos de los torneos.
 */
class UsuariosSeeder extends Seeder
{
    /**
     * @var array<int, array{nombre: string, correo: string, clave: string, rol: RolUsuario}>
     */
    private const CUENTAS = [
        [
            'nombre' => 'Administrador',
            'correo' => 'admin@juegoszone.com',
            'clave' => 'admin123',
            'rol' => RolUsuario::Administrador,
        ],
        [
            'nombre' => 'Empleado de Torneo',
            'correo' => 'empleado@juegoszone.com',
            'clave' => 'trabajador123',
            'rol' => RolUsuario::Empleado,
        ],
        [
            'nombre' => 'Carlos Ramírez',
            'correo' => 'dueno@juegoszone.com',
            'clave' => 'dueno123',
            'rol' => RolUsuario::Dueno,
        ],
        [
            'nombre' => 'Marcela Ospina',
            'correo' => 'dueno2@juegoszone.com',
            'clave' => 'dueno123',
            'rol' => RolUsuario::Dueno,
        ],
        [
            'nombre' => 'Andrés Betancur',
            'correo' => 'dueno3@juegoszone.com',
            'clave' => 'dueno123',
            'rol' => RolUsuario::Dueno,
        ],
        [
            'nombre' => 'Hincha de Prueba',
            'correo' => 'test@example.com',
            'clave' => 'password',
            'rol' => RolUsuario::Espectador,
        ],
    ];

    public function run(): void
    {
        foreach (self::CUENTAS as $cuenta) {
            Usuario::updateOrCreate(
                ['correo' => $cuenta['correo']],
                [
                    'nombre' => $cuenta['nombre'],
                    'clave' => $cuenta['clave'],
                    'rol' => $cuenta['rol'],
                    'email_verified_at' => now(),
                ],
            );
        }
    }
}
