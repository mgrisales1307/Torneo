<?php

use App\Enumeraciones\RolUsuario;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pasa la tabla de usuarios al español.
 *
 * Se hace renombrando en vez de reescribir la migración original a propósito:
 * quien ya tenga la base creada solo corre `php artisan migrate` y sigue
 * trabajando, sin perder datos ni tener que borrar el archivo SQLite. En un
 * clon nuevo el resultado es exactamente el mismo, porque esta migración corre
 * justo después de la que crea la tabla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('users', 'usuarios');

        Schema::table('usuarios', function (Blueprint $table): void {
            $table->renameColumn('name', 'nombre');
            $table->renameColumn('email', 'correo');
            $table->renameColumn('password', 'clave');
            $table->renameColumn('role', 'rol');
        });

        Schema::table('usuarios', function (Blueprint $table): void {
            $table->string('telefono', 30)->nullable()->after('correo');
            $table->softDeletes();
        });

        // El rol `user` de la primera versión pasa a llamarse `espectador`.
        DB::table('usuarios')->where('rol', 'user')->update(['rol' => RolUsuario::Espectador->value]);

        // Los tokens de Sanctum guardan el nombre de la clase del modelo.
        DB::table('personal_access_tokens')
            ->where('tokenable_type', 'App\Models\User')
            ->update(['tokenable_type' => 'App\Models\Usuario']);
    }

    public function down(): void
    {
        DB::table('personal_access_tokens')
            ->where('tokenable_type', 'App\Models\Usuario')
            ->update(['tokenable_type' => 'App\Models\User']);

        DB::table('usuarios')->where('rol', RolUsuario::Espectador->value)->update(['rol' => 'user']);

        Schema::table('usuarios', function (Blueprint $table): void {
            $table->dropSoftDeletes();
            $table->dropColumn('telefono');
        });

        Schema::table('usuarios', function (Blueprint $table): void {
            $table->renameColumn('nombre', 'name');
            $table->renameColumn('correo', 'email');
            $table->renameColumn('clave', 'password');
            $table->renameColumn('rol', 'role');
        });

        Schema::rename('usuarios', 'users');
    }
};
