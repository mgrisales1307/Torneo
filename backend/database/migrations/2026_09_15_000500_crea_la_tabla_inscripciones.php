<?php

use App\Enumeraciones\EstadoInscripcion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inscripciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('torneo_id')->constrained('torneos')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('equipo_id')->constrained('equipos')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('estado', 40)->default(EstadoInscripcion::PendientePago->value)->index();

            // Copia del precio vigente cuando se inscribió: si mañana sube el
            // costo del torneo, esta inscripción sigue debiendo lo que se pactó.
            $table->unsignedBigInteger('monto_centavos')->default(0);
            $table->char('moneda', 3)->default('COP');

            $table->string('grupo', 20)->nullable();
            $table->text('notas_revision')->nullable();
            $table->foreignId('revisado_por_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('revisado_en')->nullable();
            $table->timestamp('pagada_en')->nullable();
            $table->timestamps();

            $table->unique(['torneo_id', 'equipo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inscripciones');
    }
};
