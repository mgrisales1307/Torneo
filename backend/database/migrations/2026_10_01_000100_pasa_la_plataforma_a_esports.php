<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Cambia el dominio de la plataforma: de torneos de fútbol a torneos de
 * esports (Valorant, League of Legends, Dota 2, PUBG y Free Fire).
 *
 * Es una migración nueva, y no una reescritura de las anteriores, por la misma
 * razón que la que tradujo la tabla de usuarios: quien ya tenga la base creada
 * solo corre `php artisan migrate` y sigue trabajando. En un clon nuevo el
 * resultado es exactamente el mismo.
 *
 * Las tablas del fútbol (plantillas con dorsal, goles, tarjetas, localía) no
 * tienen equivalente en el dominio nuevo, así que se borran y se crean las
 * nuevas. Las cuentas de usuario se conservan.
 */
return new class extends Migration
{
    private const TABLAS_DEL_FUTBOL = [
        'eventos_partido',
        'partidos',
        'jugadores',
        'pagos',
        'ordenes',
        'inscripciones',
        'equipos',
        'torneos',
    ];

    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach (self::TABLAS_DEL_FUTBOL as $tabla) {
            Schema::dropIfExists($tabla);
        }

        Schema::enableForeignKeyConstraints();

        $this->adaptarUsuarios();
        $this->crearCuentasDeJuego();
        $this->crearEquipos();
        $this->crearTorneos();
        $this->crearInscripcionesYPagos();
        $this->crearCuadros();
    }

    public function down(): void
    {
        // Volver atrás no recupera el fútbol: sus datos se borraron en up().
        // Se deshace la estructura nueva para que la base quede coherente.
        Schema::disableForeignKeyConstraints();

        foreach ([
            'resultados_battle_royale',
            'reportes_de_resultado',
            'partidos',
            'fases',
            'pagos',
            'ordenes',
            'inscripciones',
            'torneos',
            'miembros_equipo',
            'equipos',
            'cuentas_de_juego',
        ] as $tabla) {
            Schema::dropIfExists($tabla);
        }

        Schema::enableForeignKeyConstraints();

        Schema::table('usuarios', function (Blueprint $table): void {
            $table->dropUnique(['apodo']);
            $table->dropColumn('apodo');
        });
    }

    /**
     * En esports todo el que se registra es jugador: puede crear equipos,
     * unirse a otros e inscribirse. Por eso los roles de dueño y espectador se
     * funden en uno solo. El capitán no es un rol de la cuenta, es un cargo
     * dentro de cada equipo.
     */
    private function adaptarUsuarios(): void
    {
        Schema::table('usuarios', function (Blueprint $table): void {
            // El apodo es el nombre público dentro de la plataforma. Admite
            // nulo en la columna solo para poder rellenar las cuentas que ya
            // existían; el registro lo exige siempre.
            $table->string('apodo', 20)->nullable()->unique()->after('nombre');
        });

        DB::table('usuarios')
            ->whereIn('rol', ['dueno', 'espectador'])
            ->update(['rol' => 'jugador']);

        $usados = [];

        foreach (DB::table('usuarios')->orderBy('id')->get(['id', 'nombre', 'correo']) as $usuario) {
            $base = Str::of($usuario->nombre ?: Str::before($usuario->correo, '@'))
                ->ascii()
                ->replaceMatches('/[^A-Za-z0-9]/', '')
                ->limit(14, '')
                ->toString() ?: 'Jugador';

            $apodo = $base;
            $sufijo = 2;

            while (in_array(Str::lower($apodo), $usados, true)) {
                $apodo = $base.$sufijo++;
            }

            $usados[] = Str::lower($apodo);

            DB::table('usuarios')->where('id', $usuario->id)->update(['apodo' => $apodo]);
        }
    }

    private function crearCuentasDeJuego(): void
    {
        Schema::create('cuentas_de_juego', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('juego', 20);

            // Riot ID, Steam ID, nombre en PUBG o UID de Free Fire, según el
            // juego. Es lo que la organización usa para verificar quién juega.
            $table->string('identificador', 60);
            $table->timestamps();

            // Una cuenta por juego para cada persona, y una cuenta de juego no
            // puede figurar a nombre de dos personas.
            $table->unique(['usuario_id', 'juego']);
            $table->unique(['juego', 'identificador']);
        });
    }

    private function crearEquipos(): void
    {
        Schema::create('equipos', function (Blueprint $table): void {
            $table->id();
            $table->string('juego', 20)->index();
            $table->foreignId('capitan_id')->constrained('usuarios')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('nombre', 60);
            $table->string('slug')->unique();
            $table->string('abreviatura', 5);
            $table->string('logo_ruta')->nullable();
            $table->char('color', 7)->default('#ff4655');

            // Quien tenga este código entra al equipo. Es aleatorio y el
            // capitán lo puede cambiar si se filtró.
            $table->string('codigo_invitacion', 12)->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('miembros_equipo', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('equipo_id')->constrained('equipos')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnUpdate()->cascadeOnDelete();
            $table->boolean('es_suplente')->default(false);
            $table->timestamps();

            $table->unique(['equipo_id', 'usuario_id']);
            $table->index('usuario_id');
        });
    }

    private function crearTorneos(): void
    {
        Schema::create('torneos', function (Blueprint $table): void {
            $table->id();
            $table->string('juego', 20)->index();
            $table->string('nombre', 120);
            $table->string('slug')->unique();
            $table->string('formato', 30);
            $table->string('estado', 30)->default('borrador')->index();
            $table->text('reglas')->nullable();

            $table->unsignedSmallInteger('cupo_maximo_equipos')->default(16);

            // Series al mejor de N. La final puede ser más larga.
            $table->unsignedTinyInteger('mejor_de')->default(1);
            $table->unsignedTinyInteger('mejor_de_final')->default(3);

            // Ajustes que solo usan algunos formatos.
            $table->unsignedTinyInteger('cantidad_de_grupos')->nullable();
            $table->unsignedTinyInteger('clasifican_por_grupo')->nullable();
            $table->unsignedTinyInteger('rondas_suizo')->nullable();
            $table->unsignedTinyInteger('partidas_battle_royale')->nullable();

            // Minutos antes del inicio en que abre el check-in. Cero: sin
            // check-in, entran todos los aprobados.
            $table->unsignedSmallInteger('check_in_minutos')->default(30);

            // El dinero se guarda en centavos: un entero nunca pierde precisión
            // al sumarse ni al viajar en JSON, un flotante sí.
            $table->unsignedBigInteger('costo_inscripcion_centavos')->default(0);
            $table->char('moneda', 3)->default('COP');

            // Premio de cada puesto, en centavos: [primero, segundo, tercero].
            $table->json('premios')->nullable();

            $table->timestamp('inscripciones_abren_en')->nullable();
            $table->timestamp('inscripciones_cierran_en')->nullable();
            $table->timestamp('inicia_en')->nullable();
            $table->timestamp('finalizado_en')->nullable();

            $table->foreignId('campeon_id')->nullable()->constrained('equipos')->nullOnDelete();
            $table->foreignId('creado_por_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Inscripciones, órdenes y pagos conservan su diseño: el cobro funciona
     * igual sea fútbol o esports. La inscripción suma el check-in y la semilla.
     */
    private function crearInscripcionesYPagos(): void
    {
        Schema::create('inscripciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('torneo_id')->constrained('torneos')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('equipo_id')->constrained('equipos')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('estado', 40)->default('pendiente_pago')->index();

            // Copia del precio vigente cuando se inscribió: si mañana sube el
            // costo del torneo, esta inscripción sigue debiendo lo que se pactó.
            $table->unsignedBigInteger('monto_centavos')->default(0);
            $table->char('moneda', 3)->default('COP');

            $table->timestamp('check_in_en')->nullable();

            // Posición de salida en el cuadro. Se asigna al iniciar el torneo.
            $table->unsignedSmallInteger('semilla')->nullable();
            $table->string('grupo', 2)->nullable();

            $table->text('notas_revision')->nullable();
            $table->foreignId('revisado_por_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('revisado_en')->nullable();
            $table->timestamp('pagada_en')->nullable();
            $table->timestamps();

            $table->unique(['torneo_id', 'equipo_id']);
        });

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

        Schema::create('pagos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('orden_id')->constrained('ordenes')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('pasarela', 40);

            // El índice único junto a `pasarela` es lo que hace idempotente al
            // webhook: si la misma notificación llega dos veces, la segunda no
            // crea nada.
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

    /**
     * Estructura de la competencia, tomada del modelo de Toornament:
     *
     *   torneo → fase → partido → (reportes | resultados de battle royale)
     *
     * Un torneo de grupos tiene dos fases (grupos y eliminación); los demás
     * formatos, una.
     */
    private function crearCuadros(): void
    {
        Schema::create('fases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('torneo_id')->constrained('torneos')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedTinyInteger('orden');
            $table->string('tipo', 30);
            $table->string('nombre', 60);
            $table->string('estado', 20)->default('en_curso');
            $table->timestamps();

            $table->unique(['torneo_id', 'orden']);
        });

        Schema::create('partidos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('torneo_id')->constrained('torneos')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('fase_id')->constrained('fases')->cascadeOnUpdate()->cascadeOnDelete();

            // Ubicación en el cuadro.
            $table->string('llave', 20)->nullable();
            $table->string('grupo', 2)->nullable();
            $table->unsignedSmallInteger('ronda');
            $table->unsignedSmallInteger('numero');
            $table->unsignedTinyInteger('mejor_de')->default(1);

            // Nulo mientras el rival salga de un partido que aún no se juega.
            // En battle royale los dos quedan nulos: juegan todos los equipos.
            $table->foreignId('equipo_a_id')->nullable()->constrained('equipos')->restrictOnDelete();
            $table->foreignId('equipo_b_id')->nullable()->constrained('equipos')->restrictOnDelete();

            // Marcador de la serie: mapas o partidas ganadas por cada lado.
            $table->unsignedTinyInteger('victorias_a')->nullable();
            $table->unsignedTinyInteger('victorias_b')->nullable();
            $table->foreignId('ganador_id')->nullable()->constrained('equipos')->restrictOnDelete();
            $table->boolean('es_descanso')->default(false);

            // A dónde va cada uno al terminar. Así avanzar en el cuadro es
            // seguir un puntero, sin recalcular la estructura.
            $table->foreignId('siguiente_ganador_id')->nullable()->constrained('partidos')->nullOnDelete();
            $table->char('siguiente_ganador_ranura', 1)->nullable();
            $table->foreignId('siguiente_perdedor_id')->nullable()->constrained('partidos')->nullOnDelete();
            $table->char('siguiente_perdedor_ranura', 1)->nullable();

            $table->string('estado', 20)->default('pendiente')->index();
            $table->timestamp('programado_para')->nullable();

            // Detalle de cada mapa o partida de la serie: [{mapa, ganador}].
            $table->json('detalle')->nullable();

            $table->foreignId('resuelto_por_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('finalizado_en')->nullable();
            $table->timestamps();

            $table->index(['torneo_id', 'fase_id', 'ronda']);
            $table->index(['equipo_a_id', 'estado']);
            $table->index(['equipo_b_id', 'estado']);
        });

        Schema::create('reportes_de_resultado', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('partido_id')->constrained('partidos')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('equipo_id')->constrained('equipos')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnUpdate()->restrictOnDelete();
            $table->unsignedTinyInteger('victorias_a');
            $table->unsignedTinyInteger('victorias_b');
            $table->json('detalle')->nullable();
            $table->string('captura_ruta')->nullable();
            $table->string('comentario', 500)->nullable();
            $table->timestamps();

            // Un reporte por equipo y partido: volver a reportar lo reemplaza.
            $table->unique(['partido_id', 'equipo_id']);
        });

        Schema::create('resultados_battle_royale', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('partido_id')->constrained('partidos')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('equipo_id')->constrained('equipos')->cascadeOnUpdate()->restrictOnDelete();
            $table->unsignedTinyInteger('posicion');
            $table->unsignedSmallInteger('bajas')->default(0);
            $table->unsignedSmallInteger('puntos')->default(0);
            $table->timestamps();

            $table->unique(['partido_id', 'equipo_id']);
            $table->unique(['partido_id', 'posicion']);
        });
    }
};
