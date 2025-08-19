<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== DEBUG AVALIAÇÕES DE EQUIPE DO LEONARDO ===\n";

$leonardo = \App\Models\Person::where('cpf', '10798101610')->first();

if ($leonardo) {
    echo "Leonardo ID: {$leonardo->id}\n";
    echo "Total de subordinados: " . \App\Models\Person::where('direct_manager_id', $leonardo->id)->count() . "\n";
    
    // Verificar todas as avaliações onde Leonardo é requester
    echo "\n=== TODAS AS AVALIAÇÕES ONDE LEONARDO É REQUESTER ===\n";
    $allRequests = \App\Models\EvaluationRequest::where('requester_person_id', $leonardo->id)
        ->with(['evaluation.evaluatedPerson', 'evaluation.form'])
        ->get();
    
    echo "Total: {$allRequests->count()}\n";
    
    foreach ($allRequests->take(10) as $req) {
        echo "\nRequest ID: {$req->id}\n";
        echo "  Avaliando: {$req->evaluation->evaluatedPerson->name} (ID: {$req->evaluation->evaluated_person_id})\n";
        echo "  Requested Person: {$req->requested_person_id}\n";
        echo "  Status: {$req->status}\n";
        echo "  Tipo: {$req->evaluation->type}\n";
        echo "  Year: {$req->evaluation->form->year}\n";
        
        // Verificar se é subordinado
        if ($req->evaluation->evaluatedPerson->direct_manager_id == $leonardo->id) {
            echo "  -> É subordinado ✓\n";
        } else {
            echo "  -> NÃO é subordinado ✗\n";
        }
    }
    
    // Verificar especificamente as avaliações dos subordinados
    echo "\n=== VERIFICAR AVALIAÇÕES DOS SUBORDINADOS ===\n";
    $subordinados = \App\Models\Person::where('direct_manager_id', $leonardo->id)->get();
    
    foreach ($subordinados->take(5) as $sub) {
        echo "\nSubordinado: {$sub->name} (ID: {$sub->id})\n";
        echo "Tipo: {$sub->bond_type}\n";
        echo "Status: {$sub->functional_status}\n";
        
        // Verificar se existe avaliação para este subordinado
        $evaluations = \App\Models\Evaluation::where('evaluated_person_id', $sub->id)
            ->whereIn('type', ['servidor', 'gestor', 'comissionado'])
            ->whereHas('form', function ($q) {
                $q->where('year', 2025);
            })
            ->with(['evaluationRequests'])
            ->get();
        
        echo "Evaluations: {$evaluations->count()}\n";
        
        foreach ($evaluations as $eval) {
            echo "  Evaluation ID: {$eval->id}, Type: {$eval->type}\n";
            
            $requestsForLeonardo = $eval->evaluationRequests->where('requester_person_id', $leonardo->id);
            echo "  Requests para Leonardo: {$requestsForLeonardo->count()}\n";
            
            foreach ($requestsForLeonardo as $req) {
                echo "    Request ID: {$req->id}, Status: {$req->status}\n";
            }
        }
    }
    
    echo "\n=== VERIFICAR SE DEVERIA TER AVALIAÇÕES DE EQUIPE ===\n";
    
    // Verificar se Leonardo pode avaliar (mesmo sendo 8 - Concursado)
    $canEvaluate = \App\Models\Person::where('id', $leonardo->id)
        ->whereNotIn('functional_status', ['AFASTADO', 'FERIAS'])
        ->exists();
    
    echo "Leonardo pode avaliar: " . ($canEvaluate ? 'SIM' : 'NÃO') . "\n";
    
    // Verificar se os subordinados podem ser avaliados
    foreach ($subordinados->take(3) as $sub) {
        $canBeEvaluated = $sub->bond_type !== '8 - Concursado' && 
                         in_array($sub->functional_status, ['TRABALHANDO', 'FERIAS', 'CEDIDO', 'AFASTADO', 'SUSPENSO']);
        
        echo "{$sub->name}: Pode ser avaliado: " . ($canBeEvaluated ? 'SIM' : 'NÃO') . 
             " (Tipo: {$sub->bond_type}, Status: {$sub->functional_status})\n";
    }
}
