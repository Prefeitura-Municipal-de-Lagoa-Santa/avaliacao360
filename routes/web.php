<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureCpfIsFilled;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::middleware(['auth', 'verified', EnsureCpfIsFilled::class])->group(function () {

    Route::get('/evaluations', [DashboardController::class, 'evaluation'])->name('evaluations');

    Route::get('/pdi', [DashboardController::class, 'pdi'])->name('pdi');

    Route::get('/reports', [DashboardController::class, 'reports'])->name('reports');

    Route::get('/calendar', [DashboardController::class, 'calendar'])->name('calendar');

    Route::get('/configs', [DashboardController::class, 'configs'])->name('configs');


    // Rota para enviar o arquivo e obter o preview
    Route::post('/users/upload/preview', [UserController::class, 'previewUpload'])
        ->middleware(['auth']) // Garanta que o middleware de autenticação esteja correto
        ->name('users.upload.preview');

    // Rota para confirmar e aplicar as mudanças do upload
    Route::post('/users/upload/confirm', [UserController::class, 'confirmUpload'])
        ->name('users.upload.confirm');

    require __DIR__ . '/settings.php';
    
    Route::get('/profile/cpf', [UserController::class, 'cpf'])->name('profile.cpf');
});

require __DIR__ . '/auth.php';
