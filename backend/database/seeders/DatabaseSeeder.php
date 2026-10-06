<?php

namespace Database\Seeders;

use App\Models\Usuario;
use Illuminate\Database\Seeder;

/**
 * Ojo: aquí no se usa WithoutModelEvents. El seeder de demostración necesita
 * los eventos de los modelos, que son los que generan los slugs y los códigos
 * de invitación.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        /*
         * ¿Es una base recién creada?
         *
         * Hay que preguntarlo ANTES de sembrar nada, porque UsuariosSeeder crea
         * cuentas y después ya no se distingue una base nueva de una que
         * alguien limpió a propósito.
         *
         * De esta pregunta depende que un reinicio del contenedor no le
         * devuelva los datos de demostración a quien acaba de borrarlos: el
         * arranque ejecuta `migrate --seed` cada vez.
         */
        $baseNueva = ! Usuario::query()->exists();

        $this->callWith(UsuariosSeeder::class, ['baseNueva' => $baseNueva]);

        if ($baseNueva) {
            $this->call(DemostracionSeeder::class);
        }
    }
}
