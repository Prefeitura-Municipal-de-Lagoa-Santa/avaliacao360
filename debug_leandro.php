<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== DEBUG LEANDRO LUCAS DOMINGOS ===\n";

// Buscar Leandro pelo CPF
$leandro = \App\Models\Person::where('cpf', '15570391606')->first();

if ($leandro) {
    echo "DADOS DE LEANDRO:\n";
    echo "ID: {$leandro->id}\n";
    echo "Nome: {$leandro->name}\n";
    echo "CPF: {$leandro->cpf}\n";
    echo "Matrícula: {$leandro->registration_number}\n";
    echo "Status Funcional: {$leandro->functional_status}\n";
    echo "Tipo de Vínculo: {$leandro->bond_type}\n";
    echo "Chefe Direto ID: {$leandro->direct_manager_id}\n";
    
    if ($leandro->directManager) {
        echo "Chefe Direto: {$leandro->directManager->name} (ID: {$leandro->directManager->id})\n";
    } else {
        echo "Chefe Direto: NENHUM\n";
    }
    
    if ($leandro->jobFunction) {
        echo "Função: {$leandro->jobFunction->name}\n";
        echo "É Gestor: " . ($leandro->jobFunction->is_manager ? 'SIM' : 'NÃO') . "\n";
    } else {
        echo "Função: NENHUMA\n";
    }
    
    echo "\n=== SUBORDINADOS DE LEANDRO ===\n";
    $subordinados = \App\Models\Person::where('direct_manager_id', $leandro->id)->get();
    echo "Total de subordinados: {$subordinados->count()}\n";
    
    foreach ($subordinados as $sub) {
        echo "- {$sub->name} (ID: {$sub->id})\n";
        echo "  Status: {$sub->functional_status}\n";
        echo "  Tipo: {$sub->bond_type}\n";
    }
    
    echo "\n=== AVALIAÇÕES DE CHEFIA ONDE LEANDRO É AVALIADOR ===\n";
    $avaliacoesChefia = \App\Models\EvaluationRequest::where('requested_person_id', $leandro->id)
        ->whereHas('evaluation', function ($q) {
            $q->where('type', 'chefia');
        })
        ->with(['evaluation.evaluatedPerson', 'evaluation.form'])
        ->get();
    
    if ($avaliacoesChefia->count() > 0) {
        foreach ($avaliacoesChefia as $req) {
            echo "Request ID: {$req->id}\n";
            echo "  Avaliando: {$req->evaluation->evaluatedPerson->name} (ID: {$req->evaluation->evaluated_person_id})\n";
            echo "  Status: {$req->status}\n";
            echo "  Evaluation ID: {$req->evaluation_id}\n";
            echo "  Tipo: {$req->evaluation->type}\n";
            echo "  Form ID: {$req->evaluation->form_id}\n";
            echo "  Year: {$req->evaluation->form->year}\n";
            echo "\n";
        }
    } else {
        echo "Leandro não tem avaliações de chefia para fazer.\n";
    }
    
    echo "\n=== AVALIAÇÕES ONDE LEANDRO É REQUESTER (EQUIPE) ===\n";
    $avaliacoesEquipe = \App\Models\EvaluationRequest::where('requester_person_id', $leandro->id)
        ->whereHas('evaluation', function ($q) {
            $q->whereIn('type', ['servidor', 'gestor', 'comissionado']);
        })
        ->with(['evaluation.evaluatedPerson', 'evaluation.form', 'requestedPerson'])
        ->get();
    
    echo "Total de avaliações onde Leandro é requester: {$avaliacoesEquipe->count()}\n";
    
    foreach ($avaliacoesEquipe as $req) {
        echo "\nRequest ID: {$req->id}\n";
        echo "  Avaliando: {$req->evaluation->evaluatedPerson->name} (ID: {$req->evaluation->evaluated_person_id})\n";
        echo "  Requested Person: {$req->requestedPerson->name} (ID: {$req->requested_person_id})\n";
        echo "  Status: {$req->status}\n";
        echo "  Tipo de Avaliação: {$req->evaluation->type}\n";
        echo "  Form Year: {$req->evaluation->form->year}\n";
        
        // Verificar se é subordinado
        if ($req->evaluation->evaluatedPerson->direct_manager_id == $leandro->id) {
            echo "  -> É subordinado de Leandro ✓\n";
        } else {
            echo "  -> NÃO é subordinado de Leandro ✗\n";
        }
    }
    
    echo "\n=== VERIFICAÇÃO DA LÓGICA DO DASHBOARD ===\n";
    
    // Reproduzir a lógica exata do DashboardController
    $currentYear = date('Y');
    $now = now();
    
    // Verificar form liberado
    $selfForm = \App\Models\Form::where('year', $currentYear)
        ->whereIn('type', ['servidor', 'gestor', 'comissionado'])
        ->where('release', true)
        ->first();
    
    if ($selfForm) {
        echo "Form encontrado: ID {$selfForm->id}, Type: {$selfForm->type}, Year: {$selfForm->year}\n";
        echo "Prazo: {$selfForm->term_first} até {$selfForm->term_end}\n";
        echo "Release: " . ($selfForm->release ? 'SIM' : 'NÃO') . "\n";
        
        $isWithinSelfStandardPeriod = $selfForm->term_first && $selfForm->term_end && 
            $now->between(
                \Carbon\Carbon::parse($selfForm->term_first)->startOfDay(), 
                \Carbon\Carbon::parse($selfForm->term_end)->endOfDay()
            );
        
        echo "Dentro do prazo padrão: " . ($isWithinSelfStandardPeriod ? 'SIM' : 'NÃO') . "\n";
        
        // Verificar form de chefia
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
            
            // Verificar se Leandro tem avaliação de chefia para fazer
            $bossEvalRequest = \App\Models\EvaluationRequest::where('requested_person_id', $leandro->id)
                ->whereHas('evaluation', function ($q) {
                    $q->where('type', 'chefia');
                })
                ->first();
            
            $bossEvaluationCompleted = $bossEvalRequest && $bossEvalRequest->status === 'completed';
            $bossEvaluationVisible = !$bossEvaluationCompleted && $isWithinBossStandardPeriod;
            
            echo "Boss evaluation visible: " . ($bossEvaluationVisible ? 'SIM' : 'NÃO') . "\n";
            echo "Boss evaluation completed: " . ($bossEvaluationCompleted ? 'SIM' : 'NÃO') . "\n";
            
            // LÓGICA CORRIGIDA: Só mostra se existe request E está no prazo
            $bossEvaluationVisibleCorrected = $bossEvalRequest && !$bossEvaluationCompleted && ($isWithinBossStandardPeriod || $isBossEvalInException);
            echo "Boss evaluation visible (CORRIGIDA): " . ($bossEvaluationVisibleCorrected ? 'SIM' : 'NÃO') . "\n";
        }
        
        // Verificar avaliações de equipe - LÓGICA CORRIGIDA
        $subordinateIds = \App\Models\Person::where('direct_manager_id', $leandro->id)->pluck('id');
        echo "IDs de subordinados: " . $subordinateIds->implode(', ') . " (total: {$subordinateIds->count()})\n";
        
        $pendingTeamRequests = \App\Models\EvaluationRequest::where('requester_person_id', $leandro->id)
            ->whereHas('evaluation', function ($q) use ($subordinateIds) {
                $q->whereIn('type', ['servidor', 'gestor', 'comissionado'])
                  ->whereIn('evaluated_person_id', $subordinateIds); // Só subordinados
            })
            ->where('status', 'pending')
            ->get();
        
        echo "Requests pendentes onde Leandro é requester: {$pendingTeamRequests->count()}\n";
        
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
                echo "  -> Evaluation Type: {$request->evaluation->type}\n";
                echo "  -> Exception dates: {$request->exception_date_first} até {$request->exception_date_end}\n";
                break;
            }
        }
        
        echo "teamEvaluationVisible: " . ($teamEvaluationVisible ? 'SIM' : 'NÃO') . "\n";
        
        $teamEvaluationCompleted = !$teamEvaluationVisible && 
            $subordinateIds->count() > 0 && 
            \App\Models\EvaluationRequest::where('requester_person_id', $leandro->id)
                ->whereHas('evaluation', function ($q) use ($subordinateIds) {
                    $q->whereIn('type', ['servidor', 'gestor', 'comissionado'])
                      ->whereIn('evaluated_person_id', $subordinateIds); // Só subordinados
                })->exists();
        
        echo "teamEvaluationCompleted: " . ($teamEvaluationCompleted ? 'SIM' : 'NÃO') . "\n";
        
        echo "\nRESULTADO FINAL:\n";
        echo "- Botão 'Avaliação Chefia' visível (ORIGINAL): " . (isset($bossEvaluationVisible) && $bossEvaluationVisible ? 'SIM' : 'NÃO') . "\n";
        echo "- Botão 'Avaliação Chefia' visível (CORRIGIDA): " . (isset($bossEvaluationVisibleCorrected) && $bossEvaluationVisibleCorrected ? 'SIM' : 'NÃO') . "\n";
        echo "- Botão 'Avaliar Equipe' visível: " . ($teamEvaluationVisible || $teamEvaluationCompleted ? 'SIM' : 'NÃO') . "\n";
        
    } else {
        echo "Form não encontrado ou não liberado\n";
    }
    
} else {
    echo "Leandro não encontrado no banco de dados com CPF 15570391606\n";
}
