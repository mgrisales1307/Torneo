<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Ojo: aquí no se usa WithoutModelEvents. El seeder de demostración necesita
 * los eventos de los modelos, que son los que generan los slugs de torneos y
 * equipos.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UsuariosSeeder::class,
            TorneoDeDemostracionSeeder::class,
        ]);
    }
}
