<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dueno_id')->constrained('usuarios')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('nombre');
            $table->string('slug')->unique();
            $table->string('abreviatura', 4);
            $table->string('ciudad', 120)->nullable();
            $table->string('estadio', 160)->nullable();
            $table->date('fundado_en')->nullable();
            $table->text('descripcion')->nullable();

            $table->string('escudo_ruta')->nullable();
            $table->string('uniforme_local_ruta')->nullable();
            $table->string('uniforme_visitante_ruta')->nullable();
            $table->char('color_primario', 7)->default('#0b1d17');
            $table->char('color_secundario', 7)->default('#eab857');

            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('dueno_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipos');
    }
};
