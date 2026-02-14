<?php

use App\Http\Controllers\Admin\AdminCustomersController;
use App\Http\Controllers\Admin\AdminInvoiceController;
use App\Http\Controllers\Admin\AdminUsers;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\CompanyController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'web'])->group(function () {
    Route::get('/admin/users', [AdminUsers::class, 'index']);
    Route::post('/admin/users', [AdminUsers::class, 'store']);
    Route::put('/admin/users/update/{id}', [AdminUsers::class, 'update']);
    Route::delete('/admin/users/{id}', [AdminUsers::class, 'destroy']);
    Route::get('/admin/users/view/{id}', [AdminUsers::class, 'view']);

    Route::get('/admin/invoices', [AdminInvoiceController::class, 'index']);
    Route::delete('/admin/invoices/{id}', [AdminInvoiceController::class, 'destroy']);

    Route::get('/admin/customers', [AdminCustomersController::class, 'index']);
    Route::delete('/admin/customers/{id}', [AdminCustomersController::class, 'destroy']);

    // Route::get('/company-settings', [CompanyController::class, 'index']);
    Route::post('/company-settings', [CompanyController::class, 'store']);
    // Route::get('/admin/company-settings/show', [CompanyController::class, 'show']);
    Route::put('/admin/company-settings/white-label', [CompanyController::class, 'updateWhiteLabel']);


    Route::get('/admin/invoices/stats', [DashboardController::class, 'stats']);
    Route::get('/admin/invoices/recent', [DashboardController::class, 'recentInvoices']);

    Route::get('/admin/dashboard-summary', [DashboardController::class, 'index']);
});
