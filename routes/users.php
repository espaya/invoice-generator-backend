<?php

use App\Http\Controllers\CustomersController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;



Route::middleware('auth:sanctum')->group(function () {
    Route::get('/invoices', [InvoiceController::class, 'index']);
    Route::post('/invoices/add', [InvoiceController::class, 'store']);
    Route::get('/get-customers', [CustomersController::class, 'index']);



    Route::get('/user/profile', [UserController::class, 'profile']);
    Route::post('/user/profile/update', [UserController::class, 'updateProfile']);
    Route::post('/user/profile/update-email', [UserController::class, 'updateEmail']);
    Route::post('/user/profile/update-password', [UserController::class, 'updatePassword']);
    Route::post('/user/profile/update-photo', [UserController::class, 'updatePhoto']);

    Route::get('/user/invoices/recent', [InvoiceController::class, 'recentInvoices']);
    Route::get('/user/invoices/stats', [InvoiceController::class, 'stats']);
});
