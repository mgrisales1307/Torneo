<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('torneos', function (Blueprint $tabla): void {
            $tabla->string('modalidad', 20)->default('equipos');
            $tabla->boolean('flujo_automatico')->default(false);
            $tabla->unsignedSmallInteger('cuenta_regresiva_segundos')->default(60);
            $tabla->timestamp('lleno_en')->nullable();
        });
        Schema::table('equipos', fn (Blueprint $tabla) => $tabla->boolean('es_individual')->default(false));
        Schema::table('inscripciones', function (Blueprint $tabla): void {
            $tabla->foreignId('usuario_inscriptor_id')->nullable()->constrained('usuarios')->restrictOnDelete();
            $tabla->string('ciclo', 36)->nullable();
        });
        foreach (DB::table('inscripciones')->join('equipos', 'equipos.id', '=', 'inscripciones.equipo_id')->get(['inscripciones.id', 'equipos.capitan_id']) as $inscripcion) {
            DB::table('inscripciones')->where('id', $inscripcion->id)->update(['usuario_inscriptor_id' => $inscripcion->capitan_id, 'ciclo' => 'historica-'.$inscripcion->id]);
        }
        Schema::create('carteras', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->foreignId('usuario_id')->constrained('usuarios')->restrictOnDelete();
            $tabla->char('moneda', 3)->default('COP');
            $tabla->bigInteger('saldo_centavos')->default(0);
            $tabla->timestamps();
            $tabla->unique(['usuario_id', 'moneda']);
        });
        Schema::create('movimientos_cartera', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->foreignId('cartera_id')->constrained('carteras')->restrictOnDelete();
            $tabla->foreignId('torneo_id')->nullable()->constrained('torneos')->restrictOnDelete();
            $tabla->foreignId('inscripcion_id')->nullable()->constrained('inscripciones')->restrictOnDelete();
            $tabla->string('referencia', 120)->unique();
            $tabla->string('tipo', 30);
            $tabla->string('descripcion', 220);
            $tabla->bigInteger('monto_centavos');
            $tabla->bigInteger('saldo_centavos');
            $tabla->timestamps();
            $tabla->index(['cartera_id', 'id']);
        });
        Schema::create('premios_entregados', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->foreignId('torneo_id')->constrained('torneos')->restrictOnDelete();
            $tabla->unsignedTinyInteger('puesto');
            $tabla->foreignId('equipo_id')->constrained('equipos')->restrictOnDelete();
            $tabla->foreignId('usuario_id')->constrained('usuarios')->restrictOnDelete();
            $tabla->unsignedBigInteger('monto_centavos');
            $tabla->timestamps();
            $tabla->unique(['torneo_id', 'puesto']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('premios_entregados');
        Schema::dropIfExists('movimientos_cartera');
        Schema::dropIfExists('carteras');
        Schema::table('inscripciones', function (Blueprint $tabla): void {
            $tabla->dropForeign(['usuario_inscriptor_id']);
            $tabla->dropColumn(['usuario_inscriptor_id', 'ciclo']);
        });
        Schema::table('equipos', fn (Blueprint $tabla) => $tabla->dropColumn('es_individual'));
        Schema::table('torneos', fn (Blueprint $tabla) => $tabla->dropColumn(['modalidad', 'flujo_automatico', 'cuenta_regresiva_segundos', 'lleno_en']));
    }
};
