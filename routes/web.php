<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FormController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\JobFunctionController;
use App\Http\Controllers\OrganizationalChartController;
use App\Http\Controllers\OrganizationalUnitController;
use App\Http\Controllers\PdiController;
use App\Http\Controllers\PasswordChangeController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\EvaluationController;
use App\Http\Controllers\ReleaseController;
use App\Http\Middleware\EnsureCpfIsFilled;
use App\Http\Middleware\RedirectIfMustChangePassword;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::redirect("/", "/dashboard");

Route::middleware(['auth', 'verified', EnsureCpfIsFilled::class, RedirectIfMustChangePassword::class])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/admin', [AdminController::class, 'index'])->name('admin');

    Route::get('/evaluations', [DashboardController::class, 'evaluation'])->name('evaluations');

    Route::get('/pdi', [DashboardController::class, 'pdi'])->name('pdi');

    Route::get('/reports', [DashboardController::class, 'reports'])->name('reports');

    Route::get('/calendar', [DashboardController::class, 'calendar'])->name('calendar');

    Route::get('/configs', [DashboardController::class, 'configs'])->name('configs');

    Route::get('/recourse', [DashboardController::class, 'recourse'])->name('recourse');

    Route::get('/configs/form/create', [FormController::class, 'create'])->name('configs.create');
    Route::post('/configs/form', [FormController::class, 'store'])->name('configs.store');
    Route::get('/configs/form/{formulario}', [FormController::class, 'show'])->name('configs.show');
    Route::get('/configs/form/{formulario}/editar', [FormController::class, 'edit'])->name('configs.edit');
    Route::put('/configs/form/{formulario}', [FormController::class, 'update'])->name('configs.update');
    Route::delete('/configs/form/{formulario}', [FormController::class, 'destroy'])->name('configs.destroy');
    Route::post('/configs/forms/prazo', [FormController::class, 'setPrazo'])->name('configs.prazo.store');
    Route::post('/configs/forms/liberar', [FormController::class, 'setLiberar'])->name('configs.liberar.store');
    // Rotas para o formulário de PDI
    Route::post('configs/pdi', [FormController::class, 'storePdi'])->name('configs.pdi.store');
    Route::put('configs/pdi/{formulario}', [FormController::class, 'updatePdi'])->name('configs.pdi.update');
    // Rota para salvar as configurações
    Route::post('/configs/store', [ConfigController::class, 'store'])->name('configs.store');
    // ROTAS PARA AVALIAÇÃO DA CHEFIA
    Route::get('/evaluations/chefia/status', [EvaluationController::class, 'checkChefiaFormStatus'])->name('evaluations.chefia.status');
    Route::get('/evaluations/chefia', [EvaluationController::class, 'showChefiaForm'])->name('evaluations.chefia.show');
    // ROTAS PARA AUTOAVALIAÇÃO
    Route::get('/evaluations/autoavaliacao/status', [EvaluationController::class, 'checkAutoavaliacaoFormStatus'])->name('evaluations.autoavaliacao.status');
    Route::get('/evaluations/autoavaliacao', [EvaluationController::class, 'showAutoavaliacaoForm'])->name('evaluations.autoavaliacao.show');
    // Rotas para salvar avaliação
    Route::post('/evaluations/{form}', [EvaluationController::class, 'store'])->name('evaluations.store');
    // Rotas para o PDI 
    Route::get('/pdi/list', [PdiController::class, 'index'])->name('pdi.index');
    Route::get('/pdi/{pdiRequest}', [PdiController::class, 'show'])->name('pdi.show');
    Route::put('/pdi/{pdiRequest}', [PdiController::class, 'update'])->name('pdi.update');
    // Em routes.php
    Route::get('/evaluations/status', [EvaluationController::class, 'checkManagerEvaluationStatus'])->name('evaluations.status');
    Route::get('/evaluations/subordinates', [EvaluationController::class, 'showSubordinatesList'])->name('evaluations.subordinates.list');
    Route::get('/evaluations/subordinates/evaluation/{evaluationRequest}', [EvaluationController::class, 'showSubordinateEvaluationForm'])->name('evaluations.subordinate.show');
    Route::post('/persons/preview', [PersonController::class, 'previewUpload'])->name('persons.preview');
    Route::post('/persons/confirm', [PersonController::class, 'confirmUpload'])->name('persons.confirm');
    Route::resource('people', PersonController::class)->except(['create', 'store', 'show', 'destroy']);

    require __DIR__ . '/settings.php';

    Route::get('/profile/cpf', [PersonController::class, 'cpf'])->name('profile.cpf');

    Route::put('/admin/roles/{role}/permissions', [AdminController::class, 'updatePermissions'])
        ->name('admin.roles.permissions.update');

    Route::get('/funcoes', [JobFunctionController::class, 'index'])->name('funcoes.index');
    Route::patch('/funcoes/{id}/type', [JobFunctionController::class, 'updateType'])->name('funcoes.updateType');

    Route::get('/evaluations/pending', [EvaluationController::class, 'pending'])
        ->name('evaluations.pending');

    Route::get('/organizational-chart', [OrganizationalChartController::class, 'index'])
        ->name('organizational-chart.index');

    Route::post('/releases-generate/{year}', [ReleaseController::class, 'generateRelease'])
        ->name('releases.generate');

     Route::post('/pdi-generate/{year}', [ReleaseController::class, 'generatePdi'])
    ->name('pdi.generate');   

    Route::get('/avaliacoes/autoavaliacao/resultado/{evaluationRequest}', [EvaluationController::class, 'showEvaluationResult'])
        ->name('evaluations.autoavaliacao.result');

    Route::get('/evaluations/my-evaluations/history', [EvaluationController::class, 'myEvaluationsHistory'])->name('evaluations.history');

    Route::get('/evaluations/my-evaluations/{evaluationRequest}', [EvaluationController::class, 'showEvaluationDetail'])
        ->name('evaluations.details');

    Route::get('/people/manual/create', [PersonController::class, 'createManual'])->name('people.manual.create');
    Route::post('/people/manual', [PersonController::class, 'storeManual'])->name('people.manual.store');
    Route::post('/evaluations/{year}/acknowledge', [EvaluationController::class, 'acknowledge'])
        ->middleware(['auth'])
        ->name('evaluations.acknowledge');

});


Route::middleware(['auth', 'verified'])->group(function () {
    Route::put('/profile/cpf', [PersonController::class, 'cpfUpdate'])->name('profile.cpf.update');
    Route::get('/trocar-senha', [PasswordChangeController::class, 'edit'])->name('password.change');
    Route::post('/trocar-senha', [PasswordChangeController::class, 'update'])->name('password.update');
});

require __DIR__ . '/auth.php';
