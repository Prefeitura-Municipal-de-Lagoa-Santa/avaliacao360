<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== DEBUG LEONARDO - AVALIAÇÃO DE EQUIPE ===\n";

$leonardo = \App\Models\Person::where('name', 'LIKE', '%LEONARDO%CAMPOS%FONSECA%')->first();

if ($leonardo) {
    echo "LEONARDO ID: {$leonardo->id}\n";
    echo "Nome: {$leonardo->name}\n";
    echo "Função: " . ($leonardo->jobFunction ? $leonardo->jobFunction->name : 'NENHUMA') . "\n";
    echo "É Gestor: " . ($leonardo->jobFunction && $leonardo->jobFunction->is_manager ? 'SIM' : 'NÃO') . "\n";
    
    echo "\n=== SUBORDINADOS DE LEONARDO ===\n";
    $subordinados = \App\Models\Person::where('direct_manager_id', $leonardo->id)->get();
    echo "Total de subordinados: {$subordinados->count()}\n";
    
    foreach ($subordinados as $sub) {
        echo "- {$sub->name} (ID: {$sub->id})\n";
        echo "  Status: {$sub->functional_status}\n";
        echo "  Tipo: {$sub->bond_type}\n";
        echo "  Função: " . ($sub->jobFunction ? $sub->jobFunction->name : 'NENHUMA') . "\n";
    }
    
    echo "\n=== AVALIAÇÕES ONDE LEONARDO É REQUESTER ===\n";
    $avaliacoesEquipe = \App\Models\EvaluationRequest::where('requester_person_id', $leonardo->id)
        ->whereHas('evaluation', function ($q) {
            $q->whereIn('type', ['servidor', 'gestor', 'comissionado']);
        })
        ->with(['evaluation.evaluatedPerson', 'evaluation.form', 'requestedPerson'])
        ->get();
    
    echo "Total de avaliações onde Leonardo é requester: {$avaliacoesEquipe->count()}\n";
    
    foreach ($avaliacoesEquipe as $req) {
        echo "\nRequest ID: {$req->id}\n";
        echo "  Avaliando: {$req->evaluation->evaluatedPerson->name} (ID: {$req->evaluation->evaluated_person_id})\n";
        echo "  Requested Person: {$req->requestedPerson->name} (ID: {$req->requested_person_id})\n";
        echo "  Status: {$req->status}\n";
        echo "  Tipo de Avaliação: {$req->evaluation->type}\n";
        echo "  Form Year: {$req->evaluation->form->year}\n";
        
        // Verificar se é subordinado
        if ($req->evaluation->evaluatedPerson->direct_manager_id == $leonardo->id) {
            echo "  -> É subordinado de Leonardo ✓\n";
        } else {
            echo "  -> NÃO é subordinado de Leonardo ✗\n";
        }
    }
    
    echo "\n=== VERIFICAÇÃO DA LÓGICA DO DASHBOARD ===\n";
    
    // Reproduzir a lógica do DashboardController
    $currentYear = date('Y');
    $selfForm = \App\Models\Form::where('year', $currentYear)
        ->whereIn('type', ['servidor', 'gestor', 'comissionado'])
        ->where('release', true)
        ->first();
    
    if ($selfForm) {
        echo "Form encontrado: ID {$selfForm->id}, Type: {$selfForm->type}, Year: {$selfForm->year}\n";
        echo "Prazo: {$selfForm->term_first} até {$selfForm->term_end}\n";
        
        $now = now();
        $isWithinSelfStandardPeriod = $selfForm->term_first && $selfForm->term_end && 
            $now->between(
                \Carbon\Carbon::parse($selfForm->term_first)->startOfDay(), 
                \Carbon\Carbon::parse($selfForm->term_end)->endOfDay()
            );
        
        echo "Dentro do prazo padrão: " . ($isWithinSelfStandardPeriod ? 'SIM' : 'NÃO') . "\n";
        
        $pendingTeamRequests = \App\Models\EvaluationRequest::where('requester_person_id', $leonardo->id)
            ->whereHas('evaluation', function ($q) {
                $q->whereIn('type', ['servidor', 'gestor', 'comissionado']);
            })
            ->where('status', 'pending')
            ->get();
        
        echo "Requests pendentes onde Leonardo é requester: {$pendingTeamRequests->count()}\n";
        
        $teamEvaluationVisible = false;
        foreach ($pendingTeamRequests as $request) {
            $isTeamEvalInException = $request->exception_date_first &&
                $request->exception_date_end &&
                $now->between(
                    \Carbon\Carbon::parse($request->exception_date_first)->startOfDay(),
                    \Carbon\Carbon::parse($request->exception_date_end)->endOfDay()
                );

            if ($isWithinSelfStandardPeriod || $isTeamEvalInException) {
                $teamEvaluationVisible = true;
                echo "Request ID {$request->id} torna teamEvaluationVisible = true\n";
                break;
            }
        }
        
        echo "teamEvaluationVisible: " . ($teamEvaluationVisible ? 'SIM' : 'NÃO') . "\n";
        
        $teamEvaluationCompleted = !$teamEvaluationVisible && \App\Models\EvaluationRequest::where('requester_person_id', $leonardo->id)
            ->whereHas('evaluation', function ($q) {
                $q->whereIn('type', ['servidor', 'gestor', 'comissionado']);
            })->exists();
        
        echo "teamEvaluationCompleted: " . ($teamEvaluationCompleted ? 'SIM' : 'NÃO') . "\n";
        
        echo "\nRESULTADO FINAL:\n";
        echo "- Botão 'Avaliar Equipe' visível: " . ($teamEvaluationVisible || $teamEvaluationCompleted ? 'SIM' : 'NÃO') . "\n";
        
    } else {
        echo "Form não encontrado ou não liberado\n";
    }
    
} else {
    echo "Leonardo não encontrado\n";
}
