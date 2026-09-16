<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Goles y tarjetas de cada partido. De aquí sale la tabla de goleadores, y el
 * marcador del partido se recalcula a partir de estos eventos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventos_partido', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('partido_id')->constrained('partidos')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('equipo_id')->constrained('equipos')->cascadeOnUpdate()->restrictOnDelete();

            // El jugador se conserva aunque lo den de baja del equipo: la baja
            // es lógica, así que el historial de goleadores nunca se pierde.
            $table->foreignId('jugador_id')->nullable()->constrained('jugadores')->cascadeOnUpdate()->nullOnDelete();

            $table->string('tipo', 40)->index();
            $table->unsignedSmallInteger('minuto')->nullable();
            $table->string('detalle', 160)->nullable();
            $table->timestamps();

            $table->index(['partido_id', 'equipo_id']);
            $table->index('jugador_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_partido');
    }
};
