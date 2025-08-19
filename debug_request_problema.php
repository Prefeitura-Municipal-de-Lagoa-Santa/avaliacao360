<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== ANÁLISE DO REQUEST PROBLEMÁTICO ===\n";

$request = \App\Models\EvaluationRequest::with(['evaluation.evaluatedPerson', 'requestedPerson'])->find(2705);

if ($request) {
    echo "REQUEST ID: {$request->id}\n";
    echo "Status: {$request->status}\n";
    echo "Requester Person ID: {$request->requester_person_id}\n";
    echo "Requested Person ID: {$request->requested_person_id}\n";
    echo "Evaluation ID: {$request->evaluation_id}\n";
    
    if ($request->requester_person_id) {
        $requester = \App\Models\Person::find($request->requester_person_id);
        echo "Requester: " . ($requester ? $requester->name : 'N/A') . " (ID: {$request->requester_person_id})\n";
    }
    
    if ($request->requestedPerson) {
        echo "Requested: {$request->requestedPerson->name} (ID: {$request->requested_person_id})\n";
    }
    
    echo "\nEVALUATION:\n";
    $evaluation = $request->evaluation;
    echo "Evaluation ID: {$evaluation->id}\n";
    echo "Type: {$evaluation->type}\n";
    echo "Evaluated Person ID: {$evaluation->evaluated_person_id}\n";
    
    if ($evaluation->evaluatedPerson) {
        echo "Evaluated Person: {$evaluation->evaluatedPerson->name} (ID: {$evaluation->evaluated_person_id})\n";
    }
    
    echo "\nANÁLISE:\n";
    echo "- Leandro (ID: 110) é o REQUESTER\n";
    echo "- Leonardo (ID: 98) é o REQUESTED\n";
    echo "- Leandro (ID: 110) é o EVALUATED\n";
    echo "\nISSO SIGNIFICA:\n";
    echo "- Leonardo deve avaliar Leandro\n";
    echo "- Mas Leandro aparece como requester, o que está confundindo a lógica\n";
    
    echo "\n=== PROBLEMA NA LÓGICA ===\n";
    echo "A lógica do dashboard está verificando:\n";
    echo "pendingTeamRequests = EvaluationRequest::where('requester_person_id', \$person->id)\n";
    echo "Isso retorna requests onde Leandro é requester, mas na verdade ele está sendo avaliado!\n";
    
    echo "\n=== SOLUÇÃO ===\n";
    echo "A lógica deveria verificar se:\n";
    echo "1. A pessoa é realmente um gestor (tem subordinados)\n";
    echo "2. As avaliações são realmente de subordinados (evaluation.evaluated_person_id deve ser subordinado)\n";
    echo "3. Não incluir autoavaliações ou avaliações onde a pessoa é avaliada\n";
}
