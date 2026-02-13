<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomersController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;



Route::middleware('auth:sanctum')->group(function () {
    Route::get('/invoices', [InvoiceController::class, 'index']);
    Route::post('/invoices/add', [InvoiceController::class, 'store']);
    Route::get('/get-customers', [CustomersController::class, 'index']);
    Route::get('/view-invoice/{invoice_number}', [InvoiceController::class, 'view']);

    Route::get('/invoice/{invoice_number}/download', [InvoiceController::class, 'downloadPdf']);
    Route::post('/invoice/{invoice_number}/send', [InvoiceController::class, 'sendInvoiceEmail']);
    Route::post('/invoice/{invoice_number}/mark-paid', [InvoiceController::class, 'markAsPaid']);
    Route::post('/invoice/{invoice_number}/duplicate', [InvoiceController::class, 'duplicateInvoice']);
    Route::post('/invoice/{invoice_number}/void', [InvoiceController::class, 'voidInvoice']);
    Route::delete('/invoice/{invoice_number}', [InvoiceController::class, 'deleteInvoice']);
    Route::post('/invoice/{invoice_number}/update', [InvoiceController::class, 'update']);

    Route::get('/user/profile', [UserController::class, 'profile']);
    Route::post('/user/profile/update', [UserController::class, 'updateProfile']);
    Route::post('/user/profile/update-email', [UserController::class, 'updateEmail']);
    Route::post('/user/profile/update-password', [UserController::class, 'updatePassword']);
    Route::post('/user/profile/update-photo', [UserController::class, 'updatePhoto']);

    Route::get('/user/invoices/recent', [InvoiceController::class, 'recentInvoices']);
    Route::get('/user/invoices/stats', [InvoiceController::class, 'stats']);



});
