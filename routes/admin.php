<?php

use App\Http\Controllers\Admin\AdminCustomersController;
use App\Http\Controllers\Admin\AdminInvoiceController;
use App\Http\Controllers\Admin\AdminUsers;
use App\Http\Controllers\CompanyController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'web'])->group(function () {
    Route::get('/admin/users', [AdminUsers::class, 'index']);
    Route::post('/admin/users', [AdminUsers::class, 'store']);
    Route::delete('/admin/users/{id}', [AdminUsers::class, 'destroy']);

    Route::get('/admin/invoices', [AdminInvoiceController::class, 'index']);
    Route::delete('/admin/invoices/{id}', [AdminInvoiceController::class, 'destroy']);

    Route::get('/admin/customers', [AdminCustomersController::class, 'index']);
    Route::delete('/admin/customers/{id}', [AdminCustomersController::class, 'destroy']);

    Route::get('/company-settings', [CompanyController::class, 'index']);
    Route::post('/company-settings', [CompanyController::class, 'store']);
});
