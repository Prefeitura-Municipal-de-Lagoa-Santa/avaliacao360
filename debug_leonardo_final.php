<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== VERIFICAÇÃO FINAL LEONARDO ===\n";

$leonardo = \App\Models\Person::where('cpf', '10798101610')->first();

if ($leonardo) {
    echo "Leonardo ID: {$leonardo->id}\n";
    
    // CORREÇÃO: Buscar requests onde Leonardo é REQUESTED (não requester)
    echo "\n=== AVALIAÇÕES ONDE LEONARDO É REQUESTED (VAI AVALIAR) ===\n";
    
    $subordinateIds = \App\Models\Person::where('direct_manager_id', $leonardo->id)->pluck('id');
    echo "Subordinados: " . $subordinateIds->implode(', ') . "\n";
    
    // Esta é a query CORRETA - Leonardo é requested_person_id
    $teamRequests = \App\Models\EvaluationRequest::where('requested_person_id', $leonardo->id)
        ->whereHas('evaluation', function ($q) use ($subordinateIds) {
            $q->whereIn('type', ['servidor', 'gestor', 'comissionado'])
              ->whereIn('evaluated_person_id', $subordinateIds); // Só subordinados
        })
        ->where('status', 'pending')
        ->with(['evaluation.evaluatedPerson'])
        ->get();
    
    echo "Team requests onde Leonardo é REQUESTED: {$teamRequests->count()}\n";
    
    foreach ($teamRequests->take(5) as $req) {
        echo "Request ID: {$req->id}\n";
        echo "  Avaliando: {$req->evaluation->evaluatedPerson->name} (ID: {$req->evaluation->evaluated_person_id})\n";
        echo "  Leonardo é: REQUESTED (vai avaliar)\n";
        echo "  Status: {$req->status}\n";
        echo "  Tipo: {$req->evaluation->type}\n";
        echo "\n";
    }
    
    echo "=== LÓGICA CORRETA DO DASHBOARD ===\n";
    
    $currentYear = date('Y');
    $now = now();
    
    // 1. AUTOAVALIAÇÃO - CORRIGIDA
    $selfForm = \App\Models\Form::where('year', $currentYear)
        ->whereIn('type', ['servidor', 'gestor', 'comissionado'])
        ->where('release', true)
        ->first();
    
    $selfEvalRequest = \App\Models\EvaluationRequest::where('requested_person_id', $leonardo->id)
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
    
    echo "1. AUTOAVALIAÇÃO:\n";
    echo "   Self eval request: " . ($selfEvalRequest ? 'SIM' : 'NÃO') . "\n";
    echo "   Visible: " . ($selfEvaluationVisible ? 'SIM' : 'NÃO') . "\n";
    
    // 2. AVALIAÇÃO DE CHEFIA - CORRIGIDA
    $bossForm = \App\Models\Form::where('year', $currentYear)->where('type', 'chefia')->where('release', true)->first();
    $isWithinBossStandardPeriod = $bossForm && $bossForm->term_first && $bossForm->term_end && 
        $now->between(
            \Carbon\Carbon::parse($bossForm->term_first)->startOfDay(), 
            \Carbon\Carbon::parse($bossForm->term_end)->endOfDay()
        );
    
    $bossEvalRequest = \App\Models\EvaluationRequest::where('requested_person_id', $leonardo->id)
        ->whereHas('evaluation', function ($q) {
            $q->where('type', 'chefia');
        })
        ->first();
    
    $bossEvaluationCompleted = $bossEvalRequest && $bossEvalRequest->status === 'completed';
    $bossEvaluationVisible = $bossEvalRequest && !$bossEvaluationCompleted && $isWithinBossStandardPeriod;
    
    echo "\n2. AVALIAÇÃO DE CHEFIA:\n";
    echo "   Boss eval request: " . ($bossEvalRequest ? 'SIM' : 'NÃO') . "\n";
    echo "   Visible: " . ($bossEvaluationVisible ? 'SIM' : 'NÃO') . "\n";
    
    // 3. AVALIAR EQUIPE - CORRIGIDA
    $teamEvaluationVisible = $teamRequests->count() > 0 && $isWithinSelfStandardPeriod;
    
    echo "\n3. AVALIAR EQUIPE:\n";
    echo "   Team requests: {$teamRequests->count()}\n";
    echo "   Dentro do prazo: " . ($isWithinSelfStandardPeriod ? 'SIM' : 'NÃO') . "\n";
    echo "   Visible: " . ($teamEvaluationVisible ? 'SIM' : 'NÃO') . "\n";
    
    echo "\n=== RESULTADO FINAL CORRETO ===\n";
    echo "Leonardo deveria ver:\n";
    echo "- Autoavaliação: " . ($selfEvaluationVisible ? 'SIM' : 'NÃO') . "\n";
    echo "- Avaliação Chefia: " . ($bossEvaluationVisible ? 'SIM' : 'NÃO') . "\n";
    echo "- Avaliar Equipe: " . ($teamEvaluationVisible ? 'SIM' : 'NÃO') . "\n";
    
} else {
    echo "Leonardo não encontrado\n";
}
