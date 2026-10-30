<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventos_metrica', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->uuid('sesion');
            $tabla->string('tipo', 8);
            $tabla->string('pagina', 40);
            $tabla->string('dispositivo', 12);
            $tabla->double('valor')->nullable();
            $tabla->timestamp('created_at')->index();
            $tabla->index(['tipo', 'created_at']);
        });
        Schema::create('configuracion_metricas', function (Blueprint $tabla): void {
            $tabla->string('clave')->primary();
            $tabla->text('valor')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_metrica');
        Schema::dropIfExists('configuracion_metricas');
    }
};
