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
 *
 * Están en dos grupos a propósito:
 *
 *   ESENCIALES    admin y empleado. Se aseguran siempre, porque sin ellas nadie
 *                 puede entrar al panel de organización y la aplicación queda a
 *                 medias.
 *   DEMOSTRACIÓN  los dueños de prueba y el espectador. Solo se crean en una
 *                 base recién nacida: si alguien limpió los datos a propósito,
 *                 un reinicio no debe devolvérselos.
 */
class UsuariosSeeder extends Seeder
{
    /**
     * @var array<int, array{nombre: string, correo: string, clave: string, rol: RolUsuario}>
     */
    private const ESENCIALES = [
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
    ];

    /**
     * @var array<int, array{nombre: string, correo: string, clave: string, rol: RolUsuario}>
     */
    private const DEMOSTRACION = [
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

    public function run(bool $baseNueva = false): void
    {
        // El segundo grupo solo en una base nueva. Quien lo decide es
        // DatabaseSeeder, antes de crear nada: es el único momento en que se
        // puede distinguir una base recién nacida de una que alguien limpió.
        $cuentas = $baseNueva
            ? array_merge(self::ESENCIALES, self::DEMOSTRACION)
            : self::ESENCIALES;

        foreach ($cuentas as $cuenta) {
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
