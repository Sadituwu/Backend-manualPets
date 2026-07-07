<?php

use App\Http\Controllers\Admin\EstadisticaController;
use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\LoteController;
use App\Http\Controllers\ManualController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:correos-publicos');
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/auth/olvide-password', [AuthController::class, 'olvidePassword'])->middleware('throttle:correos-publicos');
Route::post('/auth/restablecer-password', [AuthController::class, 'restablecerPassword']);
Route::post('/auth/reenviar-verificacion-publico', [AuthController::class, 'reenviarVerificacionPublico'])->middleware('throttle:correos-publicos');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/usuario', [AuthController::class, 'usuario']);
    Route::put('/usuario', [AuthController::class, 'actualizarPerfil']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/reenviar-verificacion', [AuthController::class, 'reenviarVerificacion'])->middleware('throttle:correos-publicos');

    Route::get('/mis-compras', [CompraController::class, 'index']);
    Route::get('/mis-compras/{id}', [CompraController::class, 'show']);
    Route::post('/compras', [CompraController::class, 'store']);

    Route::get('/manuales/{id}/descargar', [ManualController::class, 'descargar']);
});

// Catálogo público (accesible sin login; con user() opcional para marcar bloqueado/comprado)
Route::get('/lotes', [LoteController::class, 'index']);
Route::get('/lotes/{id}', [LoteController::class, 'show']);
Route::get('/manuales', [ManualController::class, 'index']);
Route::get('/manuales/{id}', [ManualController::class, 'show']);

Route::middleware(['auth:sanctum', 'rol:admin'])->prefix('admin')->group(function () {
    Route::get('/usuarios', [UsuarioController::class, 'index']);
    Route::put('/usuarios/{id}', [UsuarioController::class, 'update']);

    Route::get('/estadisticas', [EstadisticaController::class, 'index']);

    Route::post('/lotes', [LoteController::class, 'store']);
    Route::put('/lotes/{id}', [LoteController::class, 'update']);
    Route::delete('/lotes/{id}', [LoteController::class, 'destroy']);

    Route::post('/manuales', [ManualController::class, 'store']);
    Route::put('/manuales/{id}', [ManualController::class, 'update']);
    Route::delete('/manuales/{id}', [ManualController::class, 'destroy']);
});
