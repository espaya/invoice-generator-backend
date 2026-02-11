<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Route;


Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/company-settings', [CompanyController::class, 'index']);
    Route::get('/get-invoices', [InvoiceController::class, 'index']);
});

require __DIR__ . '/users.php';
require __DIR__ . '/admin.php';
