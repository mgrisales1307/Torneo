<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cada intento de pago que la pasarela nos reporta. Una orden puede acumular
 * varios: un rechazo y después uno aprobado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('orden_id')->constrained('ordenes')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('pasarela', 40);

            // Identificador de la transacción en la pasarela. El índice único
            // junto a `pasarela` es lo que hace idempotente al webhook: si la
            // misma notificación llega dos veces, la segunda no crea nada.
            $table->string('referencia_externa', 120);

            $table->string('estado', 30)->index();
            $table->unsignedBigInteger('monto_centavos');
            $table->char('moneda', 3)->default('COP');
            $table->string('metodo', 40)->nullable();
            $table->json('carga_util')->nullable();
            $table->timestamp('procesado_en')->nullable();
            $table->timestamps();

            $table->unique(['pasarela', 'referencia_externa']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
