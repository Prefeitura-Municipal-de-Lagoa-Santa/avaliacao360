<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FormController;
use App\Http\Controllers\OrganizationalChartController;
use App\Http\Controllers\OrganizationalUnitController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\EvaluationController;
use App\Http\Controllers\ReleaseController;
use App\Http\Middleware\EnsureCpfIsFilled;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::redirect("/", "/dashboard");

Route::middleware(['auth', 'verified', EnsureCpfIsFilled::class])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/evaluations', [DashboardController::class, 'evaluation'])->name('evaluations');

    Route::get('/pdi', [DashboardController::class, 'pdi'])->name('pdi');

    Route::get('/reports', [DashboardController::class, 'reports'])->name('reports');

    Route::get('/calendar', [DashboardController::class, 'calendar'])->name('calendar');

    Route::get('/configs', [DashboardController::class, 'configs'])->name('configs');

    Route::get('/configs/form/create', [FormController::class, 'create'])->name('configs.create');
    Route::post('/configs/form', [FormController::class, 'store'])->name('configs.store');
    Route::get('/configs/form/{formulario}', [FormController::class, 'show'])->name('configs.show');
    Route::get('/configs/form/{formulario}/editar', [FormController::class, 'edit'])->name('configs.edit');
    Route::put('/configs/form/{formulario}', [FormController::class, 'update'])->name('configs.update');
    Route::delete('/configs/form/{formulario}', [FormController::class, 'destroy'])->name('configs.destroy');
    Route::post('/configs/forms/prazo', [FormController::class, 'setPrazo'])->name('configs.prazo.store');
    Route::post('/configs/forms/liberar', [FormController::class, 'setLiberar'])->name('configs.liberar.store');
// ROTAS PARA AVALIAÇÃO DA CHEFIA
    Route::get('/api/evaluations/chefia/status', [App\Http\Controllers\EvaluationController::class, 'checkChefiaFormStatus'])->name('api.evaluations.chefia.status');
    Route::get('/evaluations/chefia', [EvaluationController::class, 'showChefiaForm'])->name('evaluations.chefia.show');
    Route::post('/evaluations/chefia/{form}', [EvaluationController::class, 'storeChefiaEvaluation'])->name('evaluations.chefia.store');


    // Exemplo de como ficariam as rotas
    Route::post('/persons/preview', [PersonController::class, 'previewUpload'])->name('persons.preview');
    Route::post('/persons/confirm', [PersonController::class, 'confirmUpload'])->name('persons.confirm');
    Route::resource('people', PersonController::class)->except(['create', 'store', 'show', 'destroy']);

    require __DIR__ . '/settings.php';

    Route::get('/profile/cpf', [PersonController::class, 'cpf'])->name('profile.cpf');

});
Route::put('/profile/cpf', [PersonController::class, 'cpfUpdate'])->name('profile.cpf.update');
Route::get('/organizational-chart', [OrganizationalChartController::class, 'index'])
    ->name('organizational-chart.index');

Route::post('/releases-generate/{year}', [ReleaseController::class, 'generateRelease'])
    ->name('releases.generate');
require __DIR__ . '/auth.php';
