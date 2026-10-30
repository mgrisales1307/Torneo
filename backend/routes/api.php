<?php

use App\Http\Controllers\Api\V1\Admin\InscripcionAdminController;
use App\Http\Controllers\Api\V1\Admin\MetricasAdminController;
use App\Http\Controllers\Api\V1\MetricaController;
use App\Http\Controllers\Api\V1\Admin\PartidoAdminController;
use App\Http\Controllers\Api\V1\Admin\ResumenAdminController;
use App\Http\Controllers\Api\V1\Admin\TorneoAdminController;
use App\Http\Controllers\Api\V1\Admin\UsuarioAdminController;
use App\Http\Controllers\Api\V1\AutenticacionController;
use App\Http\Controllers\Api\V1\NotificacionDePagoController;
use App\Http\Controllers\Api\V1\Panel\CarteraController;
use App\Http\Controllers\Api\V1\Panel\CuentaDeJuegoController;
use App\Http\Controllers\Api\V1\Panel\EquipoController;
use App\Http\Controllers\Api\V1\Panel\InscripcionController;
use App\Http\Controllers\Api\V1\Panel\LogoDeEquipoController;
use App\Http\Controllers\Api\V1\Panel\MiembroDeEquipoController;
use App\Http\Controllers\Api\V1\Panel\PagoController;
use App\Http\Controllers\Api\V1\Panel\PartidoController;
use App\Http\Controllers\Api\V1\Publico\EquipoPublicoController;
use App\Http\Controllers\Api\V1\Publico\InicioController;
use App\Http\Controllers\Api\V1\Publico\JuegoController;
use App\Http\Controllers\Api\V1\Publico\JugadorPublicoController;
use App\Http\Controllers\Api\V1\Publico\PartidoPublicoController;
use App\Http\Controllers\Api\V1\Publico\TorneoPublicoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1
|--------------------------------------------------------------------------
|
| La API está dividida en tres zonas, y esa división es la que hace legible el
| control de acceso:
|
|   público   — sin sesión. Solo lectura: juegos, torneos, cuadros, equipos.
|   /panel    — el jugador gestiona sus cuentas, sus equipos, sus
|               inscripciones y reporta sus partidos.
|   /admin    — la organización gestiona torneos, inscripciones y resultados.
|
| El middleware `rol` es la barrera gruesa; la fina la ponen las políticas de
| cada modelo, que son las que saben si un equipo es tuyo o si eres su capitán.
|
*/

Route::prefix('v1')->middleware('throttle:api')->group(function (): void {

    /*
    |----------------------------------------------------------------------
    | Público
    |----------------------------------------------------------------------
    */

    Route::get('/inicio', InicioController::class)->name('inicio');
    Route::post('/metricas/eventos', MetricaController::class)->middleware('throttle:metricas')->name('metricas.eventos');
    Route::get('/metricas/configuracion', fn () => response()->json(['habilitadas' => config('metricas.habilitadas')]))->name('metricas.configuracion');

    Route::get('/juegos', [JuegoController::class, 'index'])->name('juegos.index');
    Route::get('/juegos/{juego}', [JuegoController::class, 'show'])->name('juegos.show');

    Route::get('/torneos', [TorneoPublicoController::class, 'index'])->name('torneos.index');
    Route::get('/torneos/{torneo}', [TorneoPublicoController::class, 'show'])->name('torneos.show');
    Route::get('/torneos/{torneo}/equipos', [TorneoPublicoController::class, 'equipos'])->name('torneos.equipos');
    Route::get('/torneos/{torneo}/cuadro', [TorneoPublicoController::class, 'cuadro'])->name('torneos.cuadro');

    Route::get('/partidos/{partido}', [PartidoPublicoController::class, 'show'])->name('partidos.show');
    Route::get('/equipos/{equipo}', [EquipoPublicoController::class, 'show'])->name('equipos.show');
    Route::get('/jugadores/{usuario:apodo}', [JugadorPublicoController::class, 'show'])->name('jugadores.show');

    /*
    |----------------------------------------------------------------------
    | Entrada
    |----------------------------------------------------------------------
    */

    Route::post('/registro', [AutenticacionController::class, 'registro'])
        ->middleware('throttle:registro')
        ->name('registro');

    Route::post('/ingreso', [AutenticacionController::class, 'ingreso'])
        ->middleware('throttle:ingreso')
        ->name('ingreso');

    /*
    |----------------------------------------------------------------------
    | Notificaciones de pago (webhook)
    |----------------------------------------------------------------------
    |
    | Sin sesión, porque quien llama es la pasarela y no un navegador. Lo
    | primero que hace es verificar la firma del mensaje.
    |
    */

    Route::post('/pagos/notificaciones/{pasarela}', NotificacionDePagoController::class)
        ->whereIn('pasarela', array_keys((array) config('pagos.pasarelas', [])))
        ->middleware('throttle:pagos')
        ->name('pagos.notificaciones');

    /*
    |----------------------------------------------------------------------
    | Con sesión iniciada
    |----------------------------------------------------------------------
    */

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/perfil', [AutenticacionController::class, 'perfil'])->name('perfil');
        Route::post('/salida', [AutenticacionController::class, 'salida'])->name('salida');

        /*
        |------------------------------------------------------------------
        | Panel del jugador
        |------------------------------------------------------------------
        |
        | Abierto a cualquier cuenta. Lo que es solo del capitán lo decide
        | EquipoPolicy, equipo por equipo.
        |
        */

        Route::prefix('panel')->name('panel.')->group(function (): void {
            Route::get('/cartera', [CarteraController::class, 'show'])->name('cartera.show');
            Route::post('/cartera/retiros', [CarteraController::class, 'retirar'])->middleware('throttle:pagos')->name('cartera.retirar');
            Route::post('/inscripciones/{inscripcion}/pagar-cartera', [PagoController::class, 'cartera'])->middleware('throttle:pagos')->name('pagos.cartera');
            Route::get('/cuentas', [CuentaDeJuegoController::class, 'index'])->name('cuentas.index');
            Route::put('/cuentas/{juego}', [CuentaDeJuegoController::class, 'update'])->name('cuentas.update');
            Route::delete('/cuentas/{juego}', [CuentaDeJuegoController::class, 'destroy'])->name('cuentas.destroy');

            Route::get('/equipos', [EquipoController::class, 'index'])->name('equipos.index');
            Route::post('/equipos', [EquipoController::class, 'store'])->name('equipos.store');
            Route::get('/equipos/{equipo}', [EquipoController::class, 'show'])->name('equipos.show');
            Route::match(['put', 'patch'], '/equipos/{equipo}', [EquipoController::class, 'update'])->name('equipos.update');
            Route::delete('/equipos/{equipo}', [EquipoController::class, 'destroy'])->name('equipos.destroy');
            Route::post('/equipos/{equipo}/codigo', [EquipoController::class, 'nuevoCodigo'])->name('equipos.codigo');

            Route::post('/equipos/{equipo}/logo', [LogoDeEquipoController::class, 'store'])
                ->middleware('throttle:subidas')
                ->name('equipos.logo');

            Route::post('/unirse', [MiembroDeEquipoController::class, 'unirse'])
                ->middleware('throttle:unirse')
                ->name('equipos.unirse');
            Route::patch('/equipos/{equipo}/miembros/{usuario}', [MiembroDeEquipoController::class, 'update'])->name('miembros.update');
            Route::delete('/equipos/{equipo}/miembros/{usuario}', [MiembroDeEquipoController::class, 'destroy'])->name('miembros.destroy');

            Route::get('/inscripciones', [InscripcionController::class, 'index'])->name('inscripciones.index');
            Route::get('/inscripciones/{inscripcion}', [InscripcionController::class, 'show'])->name('inscripciones.show');
            Route::post('/torneos/{torneo}/inscripciones', [InscripcionController::class, 'store'])->name('inscripciones.store');
            Route::delete('/inscripciones/{inscripcion}', [InscripcionController::class, 'destroy'])->name('inscripciones.destroy');
            Route::post('/inscripciones/{inscripcion}/check-in', [InscripcionController::class, 'checkIn'])->name('inscripciones.check-in');

            Route::post('/inscripciones/{inscripcion}/orden', [PagoController::class, 'store'])
                ->middleware('throttle:pagos')
                ->name('pagos.orden');
            Route::get('/ordenes/{orden}', [PagoController::class, 'show'])->name('pagos.orden.show');

            // Solo mientras se cobre con la pasarela de desarrollo. Con una
            // pasarela real esta ruta no existe.
            if (config('pagos.pasarela') === 'simulada') {
                Route::post('/ordenes/{orden}/pagar-simulado', [PagoController::class, 'procesarConLaPasarelaSimulada'])
                    ->middleware('throttle:pagos')
                    ->name('pagos.simulado');
            }

            Route::get('/partidos', [PartidoController::class, 'index'])->name('partidos.index');
            Route::post('/partidos/{partido}/reporte', [PartidoController::class, 'reportar'])
                ->middleware('throttle:subidas')
                ->name('partidos.reporte');
        });

        /*
        |------------------------------------------------------------------
        | Panel de la organización
        |------------------------------------------------------------------
        */

        Route::prefix('admin')->name('admin.')->middleware('rol:admin,empleado')->group(function (): void {
            Route::get('/resumen', ResumenAdminController::class)->name('resumen');
            Route::get('/metricas', MetricasAdminController::class)->name('metricas');

            Route::get('/torneos', [TorneoAdminController::class, 'index'])->name('torneos.index');
            Route::post('/torneos', [TorneoAdminController::class, 'store'])->name('torneos.store');
            Route::get('/torneos/{torneo}', [TorneoAdminController::class, 'show'])->name('torneos.show');
            Route::match(['put', 'patch'], '/torneos/{torneo}', [TorneoAdminController::class, 'update'])->name('torneos.update');
            Route::delete('/torneos/{torneo}', [TorneoAdminController::class, 'destroy'])->name('torneos.destroy');
            Route::post('/torneos/{torneo}/estado', [TorneoAdminController::class, 'cambiarEstado'])->name('torneos.estado');
            Route::post('/torneos/{torneo}/iniciar', [TorneoAdminController::class, 'iniciar'])->name('torneos.iniciar');

            Route::get('/inscripciones', [InscripcionAdminController::class, 'index'])->name('inscripciones.index');
            Route::post('/inscripciones/{inscripcion}/aprobar', [InscripcionAdminController::class, 'aprobar'])->name('inscripciones.aprobar');
            Route::post('/inscripciones/{inscripcion}/rechazar', [InscripcionAdminController::class, 'rechazar'])->name('inscripciones.rechazar');

            Route::get('/partidos', [PartidoAdminController::class, 'index'])->name('partidos.index');
            Route::match(['put', 'patch'], '/partidos/{partido}', [PartidoAdminController::class, 'update'])->name('partidos.update');
            Route::post('/partidos/{partido}/resultado', [PartidoAdminController::class, 'resultado'])->name('partidos.resultado');
            Route::post('/partidos/{partido}/battle-royale', [PartidoAdminController::class, 'battleRoyale'])->name('partidos.battle-royale');

            Route::get('/usuarios', [UsuarioAdminController::class, 'index'])->name('usuarios.index');
        });
    });
});
