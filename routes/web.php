<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FormController; // Importar
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

/*
|--------------------------------------------------------------------------
| Rotas Administrativas e do Dashboard
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/evaluations', [DashboardController::class, 'evaluation'])->name('evaluations');
    Route::get('/pdi', [DashboardController::class, 'pdi'])->name('pdi');
    Route::get('/reports', [DashboardController::class, 'reports'])->name('reports');
    Route::get('/calendar', [DashboardController::class, 'calendar'])->name('calendar');

    // Rota para EXIBIR a página de Configurações Administrativas
    Route::get('/configs', [DashboardController::class, 'configs'])->name('configs');

    // 2. ROTAS DE AÇÃO: O FormController cuida das ações específicas do formulário.

    Route::get('/configs/form/create', [FormController::class, 'create'])->name('configs.create');
    Route::post('/configs/form', [FormController::class, 'store'])->name('configs.store');
    Route::get('/configs/form/{formulario}', [FormController::class, 'show'])->name('configs.show');
    Route::get('/configs/form/{formulario}/editar', [FormController::class, 'edit'])->name('configs.edit');
    Route::put('/configs/form/{formulario}', [FormController::class, 'update'])->name('configs.update');
    Route::delete('/configs/form/{formulario}', [FormController::class, 'destroy'])->name('configs.destroy');
    Route::post('/configs/forms/prazo', [FormController::class, 'setPrazo'])->name('configs.prazo.store');
    Route::post('/configs/forms/liberar', [FormController::class, 'setLiberar'])->name('configs.liberar.store');




});

// Este arquivo cuida das configurações de PERFIL do usuário
require __DIR__ . '/settings.php';

require __DIR__ . '/auth.php';