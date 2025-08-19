<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== DEBUG LEONARDO CAMPOS FONSECA LEITE ===\n";

// Buscar Leonardo pelo CPF
$leonardo = \App\Models\Person::where('cpf', '10798101610')->first();

if ($leonardo) {
    echo "DADOS DE LEONARDO:\n";
    echo "ID: {$leonardo->id}\n";
    echo "Nome: {$leonardo->name}\n";
    echo "CPF: {$leonardo->cpf}\n";
    echo "Matrícula: {$leonardo->registration_number}\n";
    echo "Status Funcional: {$leonardo->functional_status}\n";
    echo "Tipo de Vínculo: {$leonardo->bond_type}\n";
    echo "Chefe Direto ID: {$leonardo->direct_manager_id}\n";
    
    if ($leonardo->directManager) {
        echo "Chefe Direto: {$leonardo->directManager->name} (ID: {$leonardo->directManager->id})\n";
    } else {
        echo "Chefe Direto: NENHUM\n";
    }
    
    if ($leonardo->jobFunction) {
        echo "Função: {$leonardo->jobFunction->name}\n";
        echo "É Gestor: " . ($leonardo->jobFunction->is_manager ? 'SIM' : 'NÃO') . "\n";
    } else {
        echo "Função: NENHUMA\n";
    }
    
    echo "\n=== SUBORDINADOS DE LEONARDO ===\n";
    $subordinados = \App\Models\Person::where('direct_manager_id', $leonardo->id)->get();
    echo "Total de subordinados: {$subordinados->count()}\n";
    
    foreach ($subordinados->take(10) as $sub) {
        echo "- {$sub->name} (ID: {$sub->id})\n";
        echo "  Status: {$sub->functional_status}\n";
        echo "  Tipo: {$sub->bond_type}\n";
    }
    
    echo "\n=== VERIFICAÇÃO AUTOAVALIAÇÃO ===\n";
    // Leonardo é '8 - Concursado', então NÃO pode fazer autoavaliação
    echo "Tipo de vínculo: {$leonardo->bond_type}\n";
    echo "Pode fazer autoavaliação: " . ($leonardo->bond_type !== '8 - Concursado' ? 'SIM' : 'NÃO') . "\n";
    
    echo "\n=== AVALIAÇÕES DE CHEFIA ONDE LEONARDO É AVALIADOR ===\n";
    $avaliacoesChefia = \App\Models\EvaluationRequest::where('requested_person_id', $leonardo->id)
        ->whereHas('evaluation', function ($q) {
            $q->where('type', 'chefia');
        })
        ->with(['evaluation.evaluatedPerson', 'evaluation.form'])
        ->get();
    
    echo "Total de avaliações de chefia: {$avaliacoesChefia->count()}\n";
    
    foreach ($avaliacoesChefia->take(5) as $req) {
        echo "Request ID: {$req->id}\n";
        echo "  Avaliando: {$req->evaluation->evaluatedPerson->name} (ID: {$req->evaluation->evaluated_person_id})\n";
        echo "  Status: {$req->status}\n";
        echo "  Evaluation ID: {$req->evaluation_id}\n";
        echo "  Tipo: {$req->evaluation->type}\n";
        echo "  Form ID: {$req->evaluation->form_id}\n";
        echo "  Year: {$req->evaluation->form->year}\n";
        echo "\n";
    }
    
    echo "\n=== AVALIAÇÕES ONDE LEONARDO É REQUESTER (EQUIPE) ===\n";
    $avaliacoesEquipe = \App\Models\EvaluationRequest::where('requester_person_id', $leonardo->id)
        ->whereHas('evaluation', function ($q) {
            $q->whereIn('type', ['servidor', 'gestor', 'comissionado']);
        })
        ->with(['evaluation.evaluatedPerson', 'evaluation.form', 'requestedPerson'])
        ->get();
    
    echo "Total de avaliações onde Leonardo é requester: {$avaliacoesEquipe->count()}\n";
    
    foreach ($avaliacoesEquipe->take(10) as $req) {
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
    $now = now();
    
    // 1. AUTOAVALIAÇÃO
    echo "1. AUTOAVALIAÇÃO:\n";
    $selfForm = \App\Models\Form::where('year', $currentYear)
        ->whereIn('type', ['servidor', 'gestor', 'comissionado'])
        ->where('release', true)
        ->first();
    
    if ($selfForm) {
        echo "Form encontrado: ID {$selfForm->id}, Type: {$selfForm->type}\n";
        
        $selfEvalRequest = \App\Models\EvaluationRequest::where('requested_person_id', $leonardo->id)
            ->whereHas('evaluation', function ($q) {
                $q->whereIn('type', ['autoavaliação', 'autoavaliaçãoGestor', 'autoavaliaçãoComissionado']);
            })
            ->first();
        
        echo "Self eval request: " . ($selfEvalRequest ? "SIM (ID: {$selfEvalRequest->id})" : 'NÃO') . "\n";
        
        if ($selfEvalRequest) {
            $selfEvaluationCompleted = $selfEvalRequest->status === 'completed';
            echo "Completed: " . ($selfEvaluationCompleted ? 'SIM' : 'NÃO') . "\n";
        }
        
        $isWithinSelfStandardPeriod = $selfForm->term_first && $selfForm->term_end && 
            $now->between(
                \Carbon\Carbon::parse($selfForm->term_first)->startOfDay(), 
                \Carbon\Carbon::parse($selfForm->term_end)->endOfDay()
            );
        
        echo "Dentro do prazo: " . ($isWithinSelfStandardPeriod ? 'SIM' : 'NÃO') . "\n";
        
        $selfEvaluationVisible = isset($selfEvalRequest) && !($selfEvalRequest && $selfEvalRequest->status === 'completed') && $isWithinSelfStandardPeriod;
        echo "Self evaluation visible: " . ($selfEvaluationVisible ? 'SIM' : 'NÃO') . "\n";
    }
    
    // 2. AVALIAÇÃO DE CHEFIA
    echo "\n2. AVALIAÇÃO DE CHEFIA:\n";
    $bossForm = \App\Models\Form::where('year', $currentYear)->where('type', 'chefia')->where('release', true)->first();
    
    if ($bossForm) {
        echo "Boss Form encontrado: ID {$bossForm->id}\n";
        echo "Boss Form prazo: {$bossForm->term_first} até {$bossForm->term_end}\n";
        
        $isWithinBossStandardPeriod = $bossForm->term_first && $bossForm->term_end && 
            $now->between(
                \Carbon\Carbon::parse($bossForm->term_first)->startOfDay(), 
                \Carbon\Carbon::parse($bossForm->term_end)->endOfDay()
            );
        
        echo "Dentro do prazo de chefia: " . ($isWithinBossStandardPeriod ? 'SIM' : 'NÃO') . "\n";
        
        $bossEvalRequest = \App\Models\EvaluationRequest::where('requested_person_id', $leonardo->id)
            ->whereHas('evaluation', function ($q) {
                $q->where('type', 'chefia');
            })
            ->first();
        
        echo "Boss eval request: " . ($bossEvalRequest ? "SIM (ID: {$bossEvalRequest->id})" : 'NÃO') . "\n";
        
        if ($bossEvalRequest) {
            $bossEvaluationCompleted = $bossEvalRequest->status === 'completed';
            echo "Boss evaluation completed: " . ($bossEvaluationCompleted ? 'SIM' : 'NÃO') . "\n";
            
            // LÓGICA CORRIGIDA: Só mostra se existe request E está no prazo
            $bossEvaluationVisible = $bossEvalRequest && !$bossEvaluationCompleted && $isWithinBossStandardPeriod;
            echo "Boss evaluation visible: " . ($bossEvaluationVisible ? 'SIM' : 'NÃO') . "\n";
        } else {
            echo "Boss evaluation visible: NÃO (sem request)\n";
        }
    }
    
    // 3. AVALIAR EQUIPE
    echo "\n3. AVALIAR EQUIPE:\n";
    $subordinateIds = \App\Models\Person::where('direct_manager_id', $leonardo->id)->pluck('id');
    echo "IDs de subordinados: " . $subordinateIds->implode(', ') . " (total: {$subordinateIds->count()})\n";
    
    $pendingTeamRequests = \App\Models\EvaluationRequest::where('requester_person_id', $leonardo->id)
        ->whereHas('evaluation', function ($q) use ($subordinateIds) {
            $q->whereIn('type', ['servidor', 'gestor', 'comissionado'])
              ->whereIn('evaluated_person_id', $subordinateIds); // Só subordinados
        })
        ->where('status', 'pending')
        ->get();
    
    echo "Requests pendentes onde Leonardo é requester (subordinados): {$pendingTeamRequests->count()}\n";
    
    if ($pendingTeamRequests->count() > 0) {
        $isWithinSelfStandardPeriod = $selfForm && $selfForm->term_first && $selfForm->term_end && 
            $now->between(
                \Carbon\Carbon::parse($selfForm->term_first)->startOfDay(), 
                \Carbon\Carbon::parse($selfForm->term_end)->endOfDay()
            );
        
        $teamEvaluationVisible = $isWithinSelfStandardPeriod && $pendingTeamRequests->count() > 0;
        echo "Team evaluation visible: " . ($teamEvaluationVisible ? 'SIM' : 'NÃO') . "\n";
    } else {
        echo "Team evaluation visible: NÃO (sem requests pendentes)\n";
    }
    
    echo "\n=== RESULTADO FINAL ===\n";
    echo "Leonardo deveria ver:\n";
    echo "- Autoavaliação: " . ($leonardo->bond_type !== '8 - Concursado' ? 'SIM' : 'NÃO') . " (8 - Concursado não faz autoavaliação)\n";
    echo "- Avaliação Chefia: " . (isset($bossEvaluationVisible) && $bossEvaluationVisible ? 'SIM' : 'NÃO') . "\n";
    echo "- Avaliar Equipe: " . (isset($teamEvaluationVisible) && $teamEvaluationVisible ? 'SIM' : 'NÃO') . "\n";
    
} else {
    echo "Leonardo não encontrado no banco de dados com CPF 10798101610\n";
}
