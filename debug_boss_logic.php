<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== DEBUG LÓGICA BOSS EVALUATION ===\n";

$leandro = \App\Models\Person::where('cpf', '15570391606')->first();

if ($leandro) {
    echo "Leandro ID: {$leandro->id}\n";
    
    // Reproduzir exatamente a lógica do DashboardController
    $currentYear = date('Y');
    $now = now();
    
    $bossForm = \App\Models\Form::where('year', $currentYear)->where('type', 'chefia')->where('release', true)->first();
    
    if ($bossForm) {
        echo "Boss Form encontrado: ID {$bossForm->id}\n";
        echo "Prazo: {$bossForm->term_first} até {$bossForm->term_end}\n";
        
        $isWithinBossStandardPeriod = $bossForm->term_first && $bossForm->term_end && 
            $now->between(
                \Carbon\Carbon::parse($bossForm->term_first)->startOfDay(), 
                \Carbon\Carbon::parse($bossForm->term_end)->endOfDay()
            );
        
        echo "Dentro do prazo de chefia: " . ($isWithinBossStandardPeriod ? 'SIM' : 'NÃO') . "\n";
        
        // Esta é a query EXATA do DashboardController
        $bossEvalRequest = \App\Models\EvaluationRequest::where('requested_person_id', $leandro->id)
            ->whereHas('evaluation', function ($q) {
                $q->where('type', 'chefia');
            })
            ->first();
        
        echo "Boss Eval Request encontrado: " . ($bossEvalRequest ? 'SIM' : 'NÃO') . "\n";
        
        if ($bossEvalRequest) {
            echo "Request ID: {$bossEvalRequest->id}\n";
            echo "Status: {$bossEvalRequest->status}\n";
            echo "Exception dates: {$bossEvalRequest->exception_date_first} até {$bossEvalRequest->exception_date_end}\n";
            
            $bossEvaluationCompleted = $bossEvalRequest && $bossEvalRequest->status === 'completed';
            echo "Boss evaluation completed: " . ($bossEvaluationCompleted ? 'SIM' : 'NÃO') . "\n";
            
            $isBossEvalInException = $bossEvalRequest && $bossEvalRequest->exception_date_first && $bossEvalRequest->exception_date_end && 
                $now->between(
                    \Carbon\Carbon::parse($bossEvalRequest->exception_date_first)->startOfDay(), 
                    \Carbon\Carbon::parse($bossEvalRequest->exception_date_end)->endOfDay()
                );
            
            echo "Em período de exceção: " . ($isBossEvalInException ? 'SIM' : 'NÃO') . "\n";
            
            $bossEvaluationVisible = !$bossEvaluationCompleted && ($isWithinBossStandardPeriod || $isBossEvalInException);
            echo "Boss evaluation visible: " . ($bossEvaluationVisible ? 'SIM' : 'NÃO') . "\n";
            
            // Verificar qual pessoa está sendo avaliada
            $evaluation = $bossEvalRequest->evaluation;
            echo "\nPessoa sendo avaliada: {$evaluation->evaluatedPerson->name} (ID: {$evaluation->evaluated_person_id})\n";
            echo "Tipo de vínculo da pessoa avaliada: {$evaluation->evaluatedPerson->bond_type}\n";
            
        } else {
            echo "Nenhum boss eval request encontrado\n";
            
            // Vamos verificar se existem evaluations de tipo chefia no sistema
            echo "\n=== VERIFICANDO TODAS AS EVALUATIONS DE CHEFIA ===\n";
            $allChefiaEvals = \App\Models\Evaluation::where('type', 'chefia')
                ->whereHas('form', function ($q) use ($currentYear) {
                    $q->where('year', $currentYear);
                })
                ->with(['evaluatedPerson', 'evaluationRequests'])
                ->get();
            
            echo "Total de evaluations de chefia no ano {$currentYear}: {$allChefiaEvals->count()}\n";
            
            foreach ($allChefiaEvals->take(5) as $eval) {
                echo "- Evaluation ID: {$eval->id}\n";
                echo "  Pessoa avaliada: {$eval->evaluatedPerson->name} (ID: {$eval->evaluated_person_id})\n";
                echo "  Tipo de vínculo: {$eval->evaluatedPerson->bond_type}\n";
                echo "  Requests: {$eval->evaluationRequests->count()}\n";
                
                foreach ($eval->evaluationRequests as $req) {
                    echo "    Request ID: {$req->id}, Requested Person: {$req->requested_person_id}\n";
                }
                echo "\n";
            }
        }
        
    } else {
        echo "Boss Form não encontrado ou não liberado\n";
    }
}
