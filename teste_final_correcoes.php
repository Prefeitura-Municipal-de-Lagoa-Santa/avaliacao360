<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== TESTE FINAL DAS CORREÇÕES ===\n";

// Teste para Leandro (não deveria ver Chefia nem Equipe)
echo "1. LEANDRO LUCAS DOMINGOS (15570391606):\n";
$leandro = \App\Models\Person::where('cpf', '15570391606')->first();

if ($leandro) {
    // Simular a nova lógica
    $currentYear = date('Y');
    $now = now();
    
    // Autoavaliação
    $selfForm = \App\Models\Form::where('year', $currentYear)
        ->whereIn('type', ['servidor', 'gestor', 'comissionado'])
        ->where('release', true)
        ->first();
    
    $selfEvalRequest = \App\Models\EvaluationRequest::where('requested_person_id', $leandro->id)
        ->whereHas('evaluation', function ($q) {
            $q->whereIn('type', ['autoavaliação', 'autoavaliaçãoGestor', 'autoavaliaçãoComissionado']);
        })
        ->first();
    
    $isWithinSelfStandardPeriod = $selfForm && $selfForm->term_first && $selfForm->term_end && 
        $now->between(
            \Carbon\Carbon::parse($selfForm->term_first)->startOfDay(), 
            \Carbon\Carbon::parse($selfForm->term_end)->endOfDay()
        );
    
    $selfEvaluationCompleted = $selfEvalRequest && $selfEvalRequest->status === 'completed';
    $selfEvaluationVisible = $selfEvalRequest && !$selfEvaluationCompleted && $isWithinSelfStandardPeriod;
    
    // Chefia
    $bossForm = \App\Models\Form::where('year', $currentYear)->where('type', 'chefia')->where('release', true)->first();
    $isWithinBossStandardPeriod = $bossForm && $bossForm->term_first && $bossForm->term_end && 
        $now->between(
            \Carbon\Carbon::parse($bossForm->term_first)->startOfDay(), 
            \Carbon\Carbon::parse($bossForm->term_end)->endOfDay()
        );
    
    $bossEvalRequest = \App\Models\EvaluationRequest::where('requested_person_id', $leandro->id)
        ->whereHas('evaluation', function ($q) {
            $q->where('type', 'chefia');
        })
        ->first();
    
    $bossEvaluationCompleted = $bossEvalRequest && $bossEvalRequest->status === 'completed';
    $bossEvaluationVisible = $bossEvalRequest && !$bossEvaluationCompleted && $isWithinBossStandardPeriod;
    
    // Equipe
    $subordinateIds = \App\Models\Person::where('direct_manager_id', $leandro->id)->pluck('id');
    $pendingTeamRequests = \App\Models\EvaluationRequest::where('requested_person_id', $leandro->id)
        ->whereHas('evaluation', function ($q) use ($subordinateIds) {
            $q->whereIn('type', ['servidor', 'gestor', 'comissionado'])
              ->whereIn('evaluated_person_id', $subordinateIds);
        })
        ->where('status', 'pending')
        ->get();
    
    $teamEvaluationVisible = $pendingTeamRequests->count() > 0 && $isWithinSelfStandardPeriod;
    
    echo "   Autoavaliação: " . ($selfEvaluationVisible ? 'SIM' : 'NÃO') . "\n";
    echo "   Avaliação Chefia: " . ($bossEvaluationVisible ? 'SIM' : 'NÃO') . "\n";
    echo "   Avaliar Equipe: " . ($teamEvaluationVisible ? 'SIM' : 'NÃO') . "\n";
}

// Teste para Leonardo (deveria ver Chefia e Equipe, mas não Autoavaliação)
echo "\n2. LEONARDO CAMPOS FONSECA LEITE (10798101610):\n";
$leonardo = \App\Models\Person::where('cpf', '10798101610')->first();

if ($leonardo) {
    // Autoavaliação
    $selfEvalRequest = \App\Models\EvaluationRequest::where('requested_person_id', $leonardo->id)
        ->whereHas('evaluation', function ($q) {
            $q->whereIn('type', ['autoavaliação', 'autoavaliaçãoGestor', 'autoavaliaçãoComissionado']);
        })
        ->first();
    
    $selfEvaluationCompleted = $selfEvalRequest && $selfEvalRequest->status === 'completed';
    $selfEvaluationVisible = $selfEvalRequest && !$selfEvaluationCompleted && $isWithinSelfStandardPeriod;
    
    // Chefia
    $bossEvalRequest = \App\Models\EvaluationRequest::where('requested_person_id', $leonardo->id)
        ->whereHas('evaluation', function ($q) {
            $q->where('type', 'chefia');
        })
        ->first();
    
    $bossEvaluationCompleted = $bossEvalRequest && $bossEvalRequest->status === 'completed';
    $bossEvaluationVisible = $bossEvalRequest && !$bossEvaluationCompleted && $isWithinBossStandardPeriod;
    
    // Equipe
    $subordinateIds = \App\Models\Person::where('direct_manager_id', $leonardo->id)->pluck('id');
    $pendingTeamRequests = \App\Models\EvaluationRequest::where('requested_person_id', $leonardo->id)
        ->whereHas('evaluation', function ($q) use ($subordinateIds) {
            $q->whereIn('type', ['servidor', 'gestor', 'comissionado'])
              ->whereIn('evaluated_person_id', $subordinateIds);
        })
        ->where('status', 'pending')
        ->get();
    
    $teamEvaluationVisible = $pendingTeamRequests->count() > 0 && $isWithinSelfStandardPeriod;
    
    echo "   Autoavaliação: " . ($selfEvaluationVisible ? 'SIM' : 'NÃO') . "\n";
    echo "   Avaliação Chefia: " . ($bossEvaluationVisible ? 'SIM' : 'NÃO') . "\n";
    echo "   Avaliar Equipe: " . ($teamEvaluationVisible ? 'SIM' : 'NÃO') . "\n";
}

echo "\n=== RESULTADO ESPERADO ===\n";
echo "Leandro: Apenas Autoavaliação\n";
echo "Leonardo: Apenas Chefia e Equipe\n";
