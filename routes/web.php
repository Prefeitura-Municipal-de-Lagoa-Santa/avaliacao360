<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FormController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\JobFunctionController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrganizationalChartController;
use App\Http\Controllers\PdiController;

// Include debug routes
if (app()->environment(['local', 'staging'])) {
    include __DIR__ . '/debug.php';
}
use App\Http\Controllers\PasswordChangeController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\EvaluationController;
use App\Http\Controllers\EvaluationRecourseController;
use App\Http\Controllers\ReleaseController;
use App\Http\Controllers\UserRoleController;
use App\Http\Middleware\EnsureCpfIsFilled;
use App\Http\Middleware\RedirectIfMustChangePassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::redirect("/", "/dashboard");

// Healthcheck simples (sem auth) para testar proxy e container
Route::get('/health', function () {
    return response()->json(['status' => 'ok'], 200);
});

Route::middleware(['auth', 'verified', EnsureCpfIsFilled::class, RedirectIfMustChangePassword::class])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/admin', [AdminController::class, 'index'])->name('admin');

    Route::get('/evaluations', [DashboardController::class, 'evaluation'])->name('evaluations');

    Route::get('/pdi', [DashboardController::class, 'pdi'])->name('pdi');

    Route::get('/reports', [DashboardController::class, 'reports'])->name('reports');

    Route::get('/calendar', [DashboardController::class, 'calendar'])->name('calendar');

    Route::get('/configs', [DashboardController::class, 'configs'])->name('configs');

    // Debug temporário
    Route::get('/debug-user', function () {
        $user = Auth::user();
        if (!$user)
            return 'Usuário não logado';

        return [
            'name' => $user->name,
            'cpf' => $user->cpf,
            'roles' => $user->roles->pluck('name'),
            'user_can_recourse' => user_can('recourse'),
            'is_comissao' => $user->roles->pluck('name')->contains('Comissão'),
        ];
    });

    Route::get('/recourse', [DashboardController::class, 'recourse'])->name('recourse');

    Route::get('/configs/form/create', [FormController::class, 'create'])->name('configs.create');
    Route::post('/configs/form', [FormController::class, 'store'])->name('configs.form.store');
    Route::get('/configs/form/{formulario}', [FormController::class, 'show'])->name('configs.show');
    Route::get('/configs/form/{formulario}/editar', [FormController::class, 'edit'])->name('configs.edit');
    Route::put('/configs/form/{formulario}', [FormController::class, 'update'])->name('configs.form.update');
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
    // Rota para liberar avaliação
    Route::post('/evaluations/release', [EvaluationController::class, 'release'])
        ->name('evaluations.release');
    // Rotas para salvar avaliação
    Route::post('/evaluations/{form}', [EvaluationController::class, 'store'])->name('evaluations.store');
    // Rota para deletar avaliação concluída
    Route::delete('/evaluations/completed/{id}', [EvaluationController::class, 'deleteCompleted'])
        ->name('evaluations.completed.delete');
    Route::post('/evaluations/completed/{id}/invalidate', [EvaluationController::class, 'invalidateCompleted'])
        ->name('evaluations.completed.invalidate');
    Route::get('/evaluations/completed/{id}/invalidation-details', [EvaluationController::class, 'getInvalidationDetails'])
        ->name('evaluations.completed.invalidation-details');
    // Rotas para o PDI - específicas DEVEM vir antes da genérica
    Route::get('/pdi/list', [PdiController::class, 'index'])->name('pdi.index');
    Route::get('/pdi/pending', [PdiController::class, 'pending'])->name('pdi.pending');
    Route::get('/pdi/completed', [PdiController::class, 'completed'])->name('pdi.completed');
    Route::get('/pdi/{pdiRequest}', [PdiController::class, 'show'])->name('pdi.show');
    Route::put('/pdi/{pdiRequest}', [PdiController::class, 'update'])->name('pdi.update');
    // Em routes.php
    Route::get('/evaluations/status', [EvaluationController::class, 'checkManagerEvaluationStatus'])->name('evaluations.status');
    Route::get('/evaluations/subordinates', [EvaluationController::class, 'showSubordinatesList'])->name('evaluations.subordinates.list');
    Route::get('/evaluations/subordinates/evaluation/{evaluationRequest}', [EvaluationController::class, 'showSubordinateEvaluationForm'])->name('evaluations.subordinate.show');
    Route::post('/persons/preview', [PersonController::class, 'previewUpload'])->name('persons.preview');
    Route::post('/persons/confirm', [PersonController::class, 'confirmUpload'])->name('persons.confirm');
    Route::resource('people', PersonController::class)->except(['create', 'store', 'show', 'destroy']);
    
    // Rota para ver todas as avaliações de uma pessoa específica
    Route::get('/people/{person}/evaluations', [PersonController::class, 'evaluations'])->name('people.evaluations');
    
    // Rota para regerar avaliações individuais quando trocar chefe
    Route::post('/people/{person}/regenerate-evaluations', [PersonController::class, 'regenerateEvaluations'])->name('people.regenerate-evaluations');

    // Rotas do sistema de logs de atividade
    Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
    Route::get('/activity-logs/{activityLog}', [ActivityLogController::class, 'show'])->name('activity-logs.show');
    Route::delete('/activity-logs/cleanup', [ActivityLogController::class, 'cleanup'])->name('activity-logs.cleanup');

    Route::get('/profile/cpf', [PersonController::class, 'cpf'])->name('profile.cpf');

    Route::put('/admin/roles/{role}/permissions', [AdminController::class, 'updatePermissions'])
        ->name('admin.roles.permissions.update');
    
    // Rotas para gerenciamento de CPF de usuários
    Route::get('/admin/manage-user-cpf', [AdminController::class, 'manageUserCpf'])
        ->name('admin.manage-user-cpf');
    Route::put('/admin/users/{user}/cpf', [AdminController::class, 'updateUserCpf'])
        ->name('admin.users.update-cpf');

    Route::get('/funcoes', [JobFunctionController::class, 'index'])->name('funcoes.index');
    Route::patch('/funcoes/{id}/type', [JobFunctionController::class, 'updateType'])->name('funcoes.updateType');

    Route::get('/evaluations/pending', [EvaluationController::class, 'pending'])
        ->name('evaluations.pending');

    Route::get('/evaluations/completed', [EvaluationController::class, 'completed'])
        ->name('evaluations.completed');
    
    Route::get('/evaluations/completed/{id}/pdf', [EvaluationController::class, 'generatePDF'])
        ->name('evaluations.completed.pdf');

    Route::get('/organizational-chart', [OrganizationalChartController::class, 'index'])
        ->name('organizational-chart.index');

    Route::post('/releases/generate/{year}', [ReleaseController::class, 'generateRelease'])
        ->name('releases.generate');

    Route::post('/pdi/generate/{year}', [ReleaseController::class, 'generatePdi'])
        ->name('pdi.generate');

    Route::get('/avaliacoes/autoavaliacao/resultado/{evaluationRequest}', [EvaluationController::class, 'showEvaluationResult'])
        ->name('evaluations.autoavaliacao.result');

    Route::get('/evaluations/my-evaluations/history', [EvaluationController::class, 'myEvaluationsHistory'])->name('evaluations.history');

    Route::get('/evaluations/my-evaluations/{evaluationRequest}', [EvaluationController::class, 'showEvaluationDetail'])
        ->name('evaluations.details');

    Route::get('/people/manual/create', [PersonController::class, 'createManual'])->name('people.manual.create');
    Route::post('/people/manual', [PersonController::class, 'storeManual'])->name('people.manual.store');
    Route::post('/evaluations/{year}/acknowledge', [EvaluationController::class, 'acknowledge'])
        ->name('evaluations.acknowledge');

    Route::post('/evaluations/{evaluation}/recourse', [EvaluationRecourseController::class, 'store'])
        ->name('recourses.store');

    Route::get('/evaluations/{evaluation}/recourse/create', [EvaluationRecourseController::class, 'create'])
        ->name('recourses.create');

    Route::get('/recourses/{recourse}', [EvaluationRecourseController::class, 'show'])
        ->name('recourses.show');

    Route::get('/recourse/open', [EvaluationRecourseController::class, 'index'])
        ->name('recourses.index');

    Route::get('/recourses/{recourse}/review', [EvaluationRecourseController::class, 'review'])
        ->name('recourses.review');

    Route::get('/recourses/{recourse}/person-evaluations', [EvaluationRecourseController::class, 'viewPersonEvaluations'])
        ->name('recourses.personEvaluations');

    Route::post('/recourses/{recourse}/mark-analyzing', [EvaluationRecourseController::class, 'markAnalyzing'])
        ->name('recourses.markAnalyzing');

    Route::post('/recourses/{recourse}/respond', [EvaluationRecourseController::class, 'respond'])
        ->name('recourses.respond');
    // Resposta de esclarecimento (nova etapa commission_clarification)
    Route::post('/recourses/{recourse}/respond-clarification', [EvaluationRecourseController::class, 'respondClarification'])
        ->name('recourses.respondClarification');

    Route::post('/recourses/{recourse}/return', [EvaluationRecourseController::class, 'returnToPreviousInstance'])
        ->name('recourses.return');

    Route::post('/recourses/{recourse}/forward-to-commission', [EvaluationRecourseController::class, 'forwardToCommission'])
        ->name('recourses.forwardToCommission');

    Route::post('/recourses/{recourse}/assign-responsible', [EvaluationRecourseController::class, 'assignResponsible'])
        ->name('recourses.assignResponsible');

    Route::delete('/recourses/{recourse}/remove-responsible', [EvaluationRecourseController::class, 'removeResponsible'])
        ->name('recourses.removeResponsible');

    // Novas rotas para o fluxo pós-Comissão (DGP, RH, Secretário e ciência)
    Route::post('/recourses/{recourse}/forward-to-dgp', [EvaluationRecourseController::class, 'forwardToDgp'])
        ->name('recourses.forwardToDgp');
    Route::post('/recourses/{recourse}/dgp-return', [EvaluationRecourseController::class, 'dgpReturnToCommission'])
        ->name('recourses.dgpReturnToCommission');
    Route::post('/recourses/{recourse}/dgp-decision', [EvaluationRecourseController::class, 'dgpDecision'])
        ->name('recourses.dgpDecision');
    Route::post('/recourses/{recourse}/ack-first', [EvaluationRecourseController::class, 'acknowledgeFirst'])
        ->name('recourses.acknowledgeFirst');
    Route::post('/recourses/{recourse}/second-instance', [EvaluationRecourseController::class, 'requestSecondInstance'])
        ->name('recourses.requestSecondInstance');
    Route::post('/recourses/{recourse}/forward-to-secretary', [EvaluationRecourseController::class, 'forwardToSecretary'])
        ->name('recourses.forwardToSecretary');
    Route::post('/recourses/{recourse}/secretary-decision', [EvaluationRecourseController::class, 'secretaryDecision'])
        ->name('recourses.secretaryDecision');
    Route::post('/recourses/{recourse}/rh-finalize-second', [EvaluationRecourseController::class, 'rhFinalizeSecond'])
        ->name('recourses.rhFinalizeSecond');
    Route::post('/recourses/{recourse}/ack-second', [EvaluationRecourseController::class, 'acknowledgeSecond'])
        ->name('recourses.acknowledgeSecond');

    Route::get('/evaluations/unanswered', [EvaluationController::class, 'unanswered'])->name('evaluations.unanswered');
        // Rota de PDI não respondidas
    Route::get('/pdi/unanswered', [PdiController::class, 'unanswered'])->name('pdi.unanswered');
        // Rota de liberação de PDI 
    Route::post('/pdi/release', [PdiController::class, 'release'])->name('pdi.release');
    // Rotas de notificações
    Route::get('/notifications', [NotificationController::class, 'unread'])->name('notifications.unread');
    Route::get('/notifications/history', [NotificationController::class, 'index'])->name('notifications.history');
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');
    Route::post('/notifications/mark-selected-read', function (Request $request) {
        $validated = $request->validate([
            'notification_ids' => 'required|array',
            'notification_ids.*' => 'required|string|exists:notifications,id'
        ]);

        $user = $request->user();
        $count = $user->notifications()
            ->whereIn('id', $validated['notification_ids'])
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('success', "{$count} notificações marcadas como lidas");
    })->name('notifications.mark-selected-read');

    Route::post('/notifications/delete-selected', function (Request $request) {
        $validated = $request->validate([
            'notification_ids' => 'required|array',
            'notification_ids.*' => 'required|string|exists:notifications,id'
        ]);

        $user = $request->user();
        $count = $user->notifications()
            ->whereIn('id', $validated['notification_ids'])
            ->delete();

        return back()->with('success', "{$count} notificações excluídas");
    })->name('notifications.delete-selected');

    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/users/manage-roles', [UserRoleController::class, 'manageRoles'])->name('users.manage-roles');
    Route::post('/users/{user}/assign-role', [UserRoleController::class, 'assign'])->name('users.assign-role');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::put('/profile/cpf', [PersonController::class, 'cpfUpdate'])->name('profile.cpf.update');
    Route::get('/trocar-senha', [PasswordChangeController::class, 'edit'])->name('password.change');
    Route::post('/trocar-senha', [PasswordChangeController::class, 'update'])->name('password.update');
});



require __DIR__ . '/auth.php';
