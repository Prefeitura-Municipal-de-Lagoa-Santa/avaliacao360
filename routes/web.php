<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

// Route::get('dashboard', function () {
//     return Inertia::render('Dashboard');
// })->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

Route::get('/evaluations', [DashboardController::class, 'evaluation'])->name('evaluations');

Route::get('/pdi', [DashboardController::class, 'pdi'])->name('pdi');

Route::get('/reports', [DashboardController::class, 'reports'])->name('reports');

Route::get('/calendar', [DashboardController::class, 'calendar'])->name('calendar');

Route::get('/configs', [DashboardController::class, 'configs'])->name('configs');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
