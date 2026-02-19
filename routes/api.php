<?php

use App\Http\Controllers\Admin\AdminCustomersController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Route;


Route::post('/login', [AuthController::class, 'login'])->name('login');

Route::get('/ping', function () {
    return response()->json([
        "status" => "ok",
        "message" => "Laravel is running"
    ]);
});



// Route::middleware('web')->group(function () {
    Route::get('/company-settings', [CompanyController::class, 'index']);
    Route::post('/password/reset/request', [PasswordResetController::class, 'sendResetLink']);
    Route::post('/password/reset/confirm', [PasswordResetController::class, 'resetPassword']);
    Route::get('/invoice/public/{invoice_number}', [InvoiceController::class, 'publicDownload']);
// });

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/get-invoices', [InvoiceController::class, 'index']);
    Route::get('/view-invoice/{invoice_number}', [InvoiceController::class, 'view']);
    Route::get('/invoice/{invoice_number}/download', [InvoiceController::class, 'downloadPdf']);
    Route::post('/invoice/{invoice_number}/send', [InvoiceController::class, 'sendInvoiceEmail']);
    Route::post('/invoice/{invoice_number}/mark-paid', [InvoiceController::class, 'markAsPaid']);
    Route::post('/invoice/{invoice_number}/duplicate', [InvoiceController::class, 'duplicateInvoice']);
    Route::post('/invoice/{invoice_number}/void', [InvoiceController::class, 'voidInvoice']);
    Route::delete('/invoice/{invoice_number}', [InvoiceController::class, 'deleteInvoice']);
    Route::post('/invoice/{invoice_number}/update', [InvoiceController::class, 'update']);

    Route::get('/get-customer/{id}', [AdminCustomersController::class, 'view']);
    Route::put('/update-customer/{id}', [AdminCustomersController::class, 'update']);

});

require __DIR__ . '/users.php';
require __DIR__ . '/admin.php';
