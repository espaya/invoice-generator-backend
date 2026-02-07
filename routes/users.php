<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomersController;
use App\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Route;



Route::middleware('auth:sanctum')->group(function () {
    Route::get('/invoices/add', [InvoiceController::class, 'index']);
    Route::post('/invoices/add', [InvoiceController::class, 'store']);
    Route::get('/customers', [CustomersController::class, 'index']);
});
