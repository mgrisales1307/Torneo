<?php

use App\Enumeraciones\EstadoTorneo;
use App\Enumeraciones\FormatoTorneo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('torneos', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->string('slug')->unique();
            $table->string('temporada', 60)->nullable();
            $table->string('categoria', 60)->nullable();
            $table->text('descripcion')->nullable();
            $table->string('formato', 40)->default(FormatoTorneo::LigaIda->value);
            $table->string('estado', 40)->default(EstadoTorneo::Borrador->value)->index();
            $table->string('ciudad', 120)->nullable();
            $table->string('sede', 160)->nullable();
            $table->string('logo_ruta')->nullable();

            $table->unsignedSmallInteger('cupo_maximo_equipos')->default(16);
            $table->unsignedSmallInteger('minimo_jugadores_por_equipo')->default(7);
            $table->unsignedSmallInteger('maximo_jugadores_por_equipo')->default(25);

            $table->unsignedTinyInteger('puntos_por_victoria')->default(3);
            $table->unsignedTinyInteger('puntos_por_empate')->default(1);
            $table->unsignedTinyInteger('puntos_por_derrota')->default(0);

            // El dinero se guarda en centavos: un entero nunca pierde precisión
            // al sumarse ni al viajar en JSON, un flotante sí.
            $table->unsignedBigInteger('costo_inscripcion_centavos')->default(0);
            $table->char('moneda', 3)->default('COP');

            $table->timestamp('inscripciones_abren_en')->nullable();
            $table->timestamp('inscripciones_cierran_en')->nullable();
            $table->timestamp('inicia_en')->nullable();
            $table->timestamp('finaliza_en')->nullable();

            $table->foreignId('creado_por_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('torneos');
    }
};
