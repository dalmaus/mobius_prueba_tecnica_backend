<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrderController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);

    // order.owner solo en detalle y cancelación: el listado ya está acotado
    // al usuario por la propia relación.
    Route::middleware('order.owner')->group(function () {
        Route::get('/orders/{order}', [OrderController::class, 'show']);
        Route::put('/orders/{order}/cancel', [OrderController::class, 'cancel']);
    });
});
