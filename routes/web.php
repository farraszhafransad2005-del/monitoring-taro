<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardExportController;
use App\Http\Controllers\DcrImportController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('dashboard');
Route::get('/export/csv', DashboardExportController::class)->name('dashboard.export');
Route::post('/imports', [DcrImportController::class, 'store'])->name('imports.store');
