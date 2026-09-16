<?php

use App\Http\Controllers\Api\V1\Admin\CalendarioAdminController;
use App\Http\Controllers\Api\V1\Admin\InscripcionAdminController;
use App\Http\Controllers\Api\V1\Admin\PartidoAdminController;
use App\Http\Controllers\Api\V1\Admin\ResumenAdminController;
use App\Http\Controllers\Api\V1\Admin\TorneoAdminController;
use App\Http\Controllers\Api\V1\Admin\UsuarioAdminController;
use App\Http\Controllers\Api\V1\AutenticacionController;
use App\Http\Controllers\Api\V1\Dueno\EquipoController;
use App\Http\Controllers\Api\V1\Dueno\FotoDeJugadorController;
use App\Http\Controllers\Api\V1\Dueno\ImagenDeEquipoController;
use App\Http\Controllers\Api\V1\Dueno\InscripcionController;
use App\Http\Controllers\Api\V1\Dueno\JugadorController;
use App\Http\Controllers\Api\V1\Dueno\PagoController;
use App\Http\Controllers\Api\V1\NotificacionDePagoController;
use App\Http\Controllers\Api\V1\Publico\CalendarioController;
use App\Http\Controllers\Api\V1\Publico\EquipoPublicoController;
use App\Http\Controllers\Api\V1\Publico\GoleadoresController;
use App\Http\Controllers\Api\V1\Publico\InicioController;
use App\Http\Controllers\Api\V1\Publico\TablaDePosicionesController;
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
|   público   — sin sesión. Solo lectura: torneos, tabla, calendario, equipos.
|   /panel    — el dueño gestiona sus equipos, su plantilla y sus pagos.
|   /admin    — la organización gestiona torneos, inscripciones y resultados.
|
| El middleware `rol` es la barrera gruesa; la fina la ponen las políticas de
| cada modelo, que son las que saben si un equipo es tuyo.
|
*/

Route::prefix('v1')->middleware('throttle:api')->group(function (): void {

    /*
    |----------------------------------------------------------------------
    | Público
    |----------------------------------------------------------------------
    */

    Route::get('/inicio', InicioController::class)->name('inicio');

    Route::get('/torneos', [TorneoPublicoController::class, 'index'])->name('torneos.index');
    Route::get('/torneos/{torneo}', [TorneoPublicoController::class, 'show'])->name('torneos.show');
    Route::get('/torneos/{torneo}/equipos', [TorneoPublicoController::class, 'equipos'])->name('torneos.equipos');
    Route::get('/torneos/{torneo}/tabla', TablaDePosicionesController::class)->name('torneos.tabla');
    Route::get('/torneos/{torneo}/goleadores', GoleadoresController::class)->name('torneos.goleadores');
    Route::get('/torneos/{torneo}/partidos', [CalendarioController::class, 'index'])->name('torneos.partidos');

    Route::get('/partidos/{partido}', [CalendarioController::class, 'show'])->name('partidos.show');
    Route::get('/equipos/{equipo}', [EquipoPublicoController::class, 'show'])->name('equipos.show');

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
        | Panel del dueño de equipo
        |------------------------------------------------------------------
        */

        Route::prefix('panel')->name('panel.')->middleware('rol:dueno,admin,empleado')->group(function (): void {
            Route::get('/equipos', [EquipoController::class, 'index'])->name('equipos.index');
            Route::post('/equipos', [EquipoController::class, 'store'])->name('equipos.store');
            Route::get('/equipos/{equipo}', [EquipoController::class, 'show'])->name('equipos.show');
            Route::match(['put', 'patch'], '/equipos/{equipo}', [EquipoController::class, 'update'])->name('equipos.update');
            Route::delete('/equipos/{equipo}', [EquipoController::class, 'destroy'])->name('equipos.destroy');

            Route::post('/equipos/{equipo}/imagenes', [ImagenDeEquipoController::class, 'store'])
                ->middleware('throttle:subidas')
                ->name('equipos.imagenes');

            Route::get('/equipos/{equipo}/jugadores', [JugadorController::class, 'index'])->name('jugadores.index');
            Route::post('/equipos/{equipo}/jugadores', [JugadorController::class, 'store'])->name('jugadores.store');
            Route::match(['put', 'patch'], '/jugadores/{jugador}', [JugadorController::class, 'update'])->name('jugadores.update');
            Route::delete('/jugadores/{jugador}', [JugadorController::class, 'destroy'])->name('jugadores.destroy');
            Route::post('/jugadores/{jugador}/foto', [FotoDeJugadorController::class, 'store'])
                ->middleware('throttle:subidas')
                ->name('jugadores.foto');

            Route::get('/inscripciones', [InscripcionController::class, 'index'])->name('inscripciones.index');
            Route::get('/inscripciones/{inscripcion}', [InscripcionController::class, 'show'])->name('inscripciones.show');
            Route::post('/torneos/{torneo}/inscripciones', [InscripcionController::class, 'store'])->name('inscripciones.store');
            Route::delete('/inscripciones/{inscripcion}', [InscripcionController::class, 'destroy'])->name('inscripciones.destroy');

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
        });

        /*
        |------------------------------------------------------------------
        | Panel de la organización
        |------------------------------------------------------------------
        */

        Route::prefix('admin')->name('admin.')->middleware('rol:admin,empleado')->group(function (): void {
            Route::get('/resumen', ResumenAdminController::class)->name('resumen');

            Route::get('/torneos', [TorneoAdminController::class, 'index'])->name('torneos.index');
            Route::post('/torneos', [TorneoAdminController::class, 'store'])->name('torneos.store');
            Route::get('/torneos/{torneo}', [TorneoAdminController::class, 'show'])->name('torneos.show');
            Route::match(['put', 'patch'], '/torneos/{torneo}', [TorneoAdminController::class, 'update'])->name('torneos.update');
            Route::delete('/torneos/{torneo}', [TorneoAdminController::class, 'destroy'])->name('torneos.destroy');

            Route::post('/torneos/{torneo}/calendario', [CalendarioAdminController::class, 'store'])->name('torneos.calendario');

            Route::get('/inscripciones', [InscripcionAdminController::class, 'index'])->name('inscripciones.index');
            Route::post('/inscripciones/{inscripcion}/aprobar', [InscripcionAdminController::class, 'aprobar'])->name('inscripciones.aprobar');
            Route::post('/inscripciones/{inscripcion}/rechazar', [InscripcionAdminController::class, 'rechazar'])->name('inscripciones.rechazar');

            Route::match(['put', 'patch'], '/partidos/{partido}', [PartidoAdminController::class, 'update'])->name('partidos.update');
            Route::post('/partidos/{partido}/resultado', [PartidoAdminController::class, 'registrarResultado'])->name('partidos.resultado');

            Route::get('/usuarios', [UsuarioAdminController::class, 'index'])->name('usuarios.index');
        });
    });
});
