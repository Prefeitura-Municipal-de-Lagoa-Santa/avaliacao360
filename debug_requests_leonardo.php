<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== VERIFICAR COMO AS AVALIAÇÕES FORAM CRIADAS ===\n";

$leonardo = \App\Models\Person::where('cpf', '10798101610')->first();

if ($leonardo) {
    // Verificar uma evaluation específica de um subordinado
    $samuel = \App\Models\Person::find(97); // SAMUEL VIEIRA DA SILVA
    echo "Subordinado: {$samuel->name} (ID: {$samuel->id})\n";
    echo "Chefe: {$samuel->direct_manager_id}\n";
    
    $evaluation = \App\Models\Evaluation::where('evaluated_person_id', $samuel->id)
        ->where('type', 'gestor')
        ->whereHas('form', function ($q) {
            $q->where('year', 2025);
        })
        ->with(['evaluationRequests'])
        ->first();
    
    if ($evaluation) {
        echo "\nEvaluation ID: {$evaluation->id}\n";
        echo "Type: {$evaluation->type}\n";
        echo "Evaluated Person: {$evaluation->evaluated_person_id}\n";
        echo "Form ID: {$evaluation->form_id}\n";
        
        echo "\nTodos os EvaluationRequests:\n";
        foreach ($evaluation->evaluationRequests as $req) {
            echo "Request ID: {$req->id}\n";
            echo "  Requester Person ID: {$req->requester_person_id}\n";
            echo "  Requested Person ID: {$req->requested_person_id}\n";
            echo "  Status: {$req->status}\n";
            
            if ($req->requester_person_id) {
                $requester = \App\Models\Person::find($req->requester_person_id);
                echo "  Requester: " . ($requester ? $requester->name : 'N/A') . "\n";
            }
            
            if ($req->requested_person_id) {
                $requested = \App\Models\Person::find($req->requested_person_id);
                echo "  Requested: " . ($requested ? $requested->name : 'N/A') . "\n";
            }
            echo "\n";
        }
        
        echo "=== ANÁLISE ===\n";
        echo "Esta é uma avaliação onde:\n";
        echo "- {$samuel->name} está sendo avaliado\n";
        echo "- O chefe direto é Leonardo (ID: {$leonardo->id})\n";
        echo "- Deveria haver um EvaluationRequest onde:\n";
        echo "  * requester_person_id = {$samuel->id} (o avaliado)\n";
        echo "  * requested_person_id = {$leonardo->id} (o chefe que vai avaliar)\n";
        
        // Verificar se existe esse request específico
        $correctRequest = $evaluation->evaluationRequests
            ->where('requester_person_id', $samuel->id)
            ->where('requested_person_id', $leonardo->id)
            ->first();
        
        if ($correctRequest) {
            echo "\n✓ Request correto encontrado: ID {$correctRequest->id}\n";
        } else {
            echo "\n✗ Request correto NÃO encontrado!\n";
            echo "Isso explica por que Leonardo não vê o botão 'Avaliar Equipe'\n";
        }
    }
    
    echo "\n=== VERIFICAR LÓGICA DE GERAÇÃO ===\n";
    echo "Leonardo (ID: {$leonardo->id}):\n";
    echo "- Tipo: {$leonardo->bond_type}\n";
    echo "- Status: {$leonardo->functional_status}\n";
    echo "- Função: " . ($leonardo->jobFunction ? $leonardo->jobFunction->name : 'NENHUMA') . "\n";
    echo "- É Gestor: " . ($leonardo->jobFunction && $leonardo->jobFunction->is_manager ? 'SIM' : 'NÃO') . "\n";
    
    // Verificar se Leonardo pode avaliar segundo as regras
    echo "\nSegundo as regras do GenerateEvaluationsJob:\n";
    echo "- pessoasQuePodemAvaliar(): Leonardo pode avaliar subordinados?\n";
    
    // Simular a query pessoasQuePodemAvaliar()
    $canEvaluate = \App\Models\Person::where('id', $leonardo->id)
        ->whereNotIn('functional_status', ['AFASTADO', 'FERIAS'])
        ->exists();
    
    echo "  Não está AFASTADO/FERIAS: " . ($canEvaluate ? 'SIM' : 'NÃO') . "\n";
    
    // Verificar se ele está na lista de pessoas elegíveis para ser avaliador
    $isEligibleEvaluator = \App\Models\Person::where('id', $leonardo->id)
        ->whereIn('functional_status', ['TRABALHANDO', 'FERIAS', 'CEDIDO', 'AFASTADO'])
        ->where(function ($query) {
            $query->where('bond_type', '!=', '3 - Concursado')
                  ->orWhereNotNull('job_function_id');
        })
        ->exists();
    
    echo "  É elegível para avaliar: " . ($isEligibleEvaluator ? 'SIM' : 'NÃO') . "\n";
    echo "  (Inclui 8 - Concursado porque eles podem avaliar)\n";
}
