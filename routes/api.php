<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;


Route::middleware(['web'])->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});


Route::middleware(['auth:sanctum', 'web'])->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
});
