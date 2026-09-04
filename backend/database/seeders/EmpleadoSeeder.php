<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class EmpleadoSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'empleado@juegoszone.com'],
            [
                'name' => 'Empleado de Torneo',
                'password' => Hash::make('CambiaEstaClave123'),
                'role' => 'empleado',
            ]
        );
    }
}
