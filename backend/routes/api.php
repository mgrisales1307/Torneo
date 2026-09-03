<?php
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

// Rutas públicas
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Rutas protegidas (requieren estar logueado)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Rutas solo para admin y empleado
    Route::middleware('role:admin,empleado')->group(function () {
        Route::middleware('role:admin,empleado')->group(function () {
    Route::get('/usuarios', [UserController::class, 'index']);
});
        // aquí irán rutas de administración de la página
        // ejemplo: Route::post('/torneos', [TorneoController::class, 'store']);
    });

    // Rutas solo para admin
    Route::middleware('role:admin')->group(function () {
        // aquí irán rutas exclusivas del admin
        // ejemplo: Route::delete('/usuarios/{id}', [UserController::class, 'destroy']);
    });
});