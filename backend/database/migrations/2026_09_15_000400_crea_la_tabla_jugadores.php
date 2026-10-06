<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jugadores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('equipo_id')->constrained('equipos')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('nombres', 120);
            $table->string('apellidos', 120);
            $table->string('documento_identidad', 40);
            $table->date('fecha_nacimiento');
            $table->string('posicion', 30)->default('mediocampista');

            // Nullable a propósito: al dar de baja a un jugador se libera su
            // dorsal para que otro pueda usarlo, sin romper el índice único ni
            // perder el historial de goles del jugador retirado.
            $table->unsignedTinyInteger('numero_camiseta')->nullable();

            $table->string('estado', 30)->default('activo');
            $table->boolean('es_capitan')->default(false);
            $table->string('foto_ruta')->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('correo')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['equipo_id', 'numero_camiseta']);
            $table->index(['equipo_id', 'documento_identidad']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jugadores');
    }
};
