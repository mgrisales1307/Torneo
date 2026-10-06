<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Orden de cobro de una inscripción: lo que el dueño del equipo debe pagar.
 * Es la unidad que se le muestra y la que la pasarela confirma.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordenes', function (Blueprint $table): void {
            $table->id();

            // Referencia pública de la orden. Es aleatoria y no secuencial para
            // que nadie pueda adivinar ni contar las órdenes de los demás.
            $table->string('referencia', 40)->unique();

            $table->foreignId('inscripcion_id')->constrained('inscripciones')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnUpdate()->restrictOnDelete();

            $table->unsignedBigInteger('monto_centavos');
            $table->char('moneda', 3)->default('COP');
            $table->string('estado', 30)->default('pendiente')->index();
            $table->string('pasarela', 40);

            $table->timestamp('expira_en')->nullable();
            $table->timestamp('pagada_en')->nullable();
            $table->timestamps();

            $table->index(['inscripcion_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordenes');
    }
};
