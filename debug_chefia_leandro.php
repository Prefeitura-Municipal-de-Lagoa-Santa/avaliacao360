<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== VERIFICAÇÃO DA AVALIAÇÃO DE CHEFIA ===\n";

$leandro = \App\Models\Person::where('cpf', '15570391606')->first();

if ($leandro) {
    echo "Leandro ID: {$leandro->id}\n";
    echo "Chefe Direto ID: {$leandro->direct_manager_id}\n";
    
    if ($leandro->directManager) {
        echo "Chefe: {$leandro->directManager->name} (ID: {$leandro->directManager->id})\n";
        echo "Status do Chefe: {$leandro->directManager->functional_status}\n";
        echo "Tipo de Vínculo do Chefe: {$leandro->directManager->bond_type}\n";
    }
    
    echo "\n=== AVALIAÇÕES DE CHEFIA ===\n";
    $bossEvalRequest = \App\Models\EvaluationRequest::where('requested_person_id', $leandro->id)
        ->whereHas('evaluation', function ($q) {
            $q->where('type', 'chefia');
        })
        ->with(['evaluation.evaluatedPerson', 'evaluation.form'])
        ->first();
    
    if ($bossEvalRequest) {
        echo "ENCONTROU REQUEST DE AVALIAÇÃO DE CHEFIA:\n";
        echo "Request ID: {$bossEvalRequest->id}\n";
        echo "Status: {$bossEvalRequest->status}\n";
        echo "Requested Person (quem vai avaliar): {$bossEvalRequest->requested_person_id} (Leandro)\n";
        echo "Requester Person (quem solicitou): {$bossEvalRequest->requester_person_id}\n";
        echo "Evaluation ID: {$bossEvalRequest->evaluation_id}\n";
        
        $evaluation = $bossEvalRequest->evaluation;
        echo "\nEVALUATION:\n";
        echo "Evaluated Person ID: {$evaluation->evaluated_person_id}\n";
        echo "Evaluated Person: {$evaluation->evaluatedPerson->name}\n";
        echo "Type: {$evaluation->type}\n";
        echo "Form ID: {$evaluation->form_id}\n";
        
        echo "\nANÁLISE:\n";
        echo "- Leandro (ID: {$leandro->id}) deve avaliar: {$evaluation->evaluatedPerson->name} (ID: {$evaluation->evaluated_person_id})\n";
        
        if ($evaluation->evaluated_person_id == $leandro->direct_manager_id) {
            echo "- ✓ É o chefe direto do Leandro\n";
        } else {
            echo "- ✗ NÃO é o chefe direto do Leandro\n";
        }
        
        // Verificar se o chefe pode ser avaliado
        echo "\n=== VERIFICAR SE O CHEFE PODE SER AVALIADO ===\n";
        $chefe = $evaluation->evaluatedPerson;
        echo "Chefe: {$chefe->name}\n";
        echo "Status: {$chefe->functional_status}\n";
        echo "Tipo de Vínculo: {$chefe->bond_type}\n";
        
        // Verificar se está na lista de pessoas elegíveis para avaliação
        $canBeEvaluated = \App\Models\Person::where('id', $chefe->id)
            ->where('bond_type', '!=', '8 - Concursado')
            ->whereIn('functional_status', ['TRABALHANDO', 'FERIAS', 'CEDIDO', 'AFASTADO', 'SUSPENSO'])
            ->exists();
        
        echo "Pode ser avaliado: " . ($canBeEvaluated ? 'SIM' : 'NÃO') . "\n";
        
        if (!$canBeEvaluated) {
            echo "*** PROBLEMA: O chefe NÃO pode ser avaliado! ***\n";
            echo "Motivo provável: Tipo de vínculo '8 - Concursado' não pode ser avaliado\n";
        }
        
    } else {
        echo "NÃO encontrou request de avaliação de chefia para Leandro\n";
    }
    
} else {
    echo "Leandro não encontrado\n";
}
