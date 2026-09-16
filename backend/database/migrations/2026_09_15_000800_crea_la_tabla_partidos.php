<?php

use App\Enumeraciones\EstadoPartido;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partidos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('torneo_id')->constrained('torneos')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedSmallInteger('jornada');
            $table->boolean('es_vuelta')->default(false);

            $table->foreignId('equipo_local_id')->constrained('equipos')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('equipo_visitante_id')->constrained('equipos')->cascadeOnUpdate()->restrictOnDelete();

            $table->timestamp('programado_para')->nullable();
            $table->string('sede', 160)->nullable();
            $table->string('arbitro', 160)->nullable();
            $table->string('estado', 30)->default(EstadoPartido::Programado->value)->index();

            $table->unsignedTinyInteger('goles_local')->nullable();
            $table->unsignedTinyInteger('goles_visitante')->nullable();
            $table->text('observaciones')->nullable();

            $table->foreignId('registrado_por_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('finalizado_en')->nullable();
            $table->timestamps();

            $table->index(['torneo_id', 'jornada']);
            $table->index(['torneo_id', 'programado_para']);

            // Dos equipos no pueden tener dos veces el mismo cruce en la misma
            // condición dentro de un torneo.
            $table->unique(['torneo_id', 'equipo_local_id', 'equipo_visitante_id', 'es_vuelta'], 'partidos_cruce_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partidos');
    }
};
