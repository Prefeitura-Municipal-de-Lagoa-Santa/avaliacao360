<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== DEBUG AVALIAÇÕES CHEFIA ===\n";

// Busca pessoas que têm avaliação tipo chefia
echo "Pessoas com avaliação tipo 'chefia':\n";
$chefiaEvals = \App\Models\Evaluation::where('type', 'chefia')
    ->with(['evaluationRequests', 'evaluatedPerson'])
    ->limit(5)
    ->get();

foreach ($chefiaEvals as $eval) {
    echo "- Evaluation ID: {$eval->id}\n";
    echo "  Person ID: {$eval->evaluated_person_id}\n";
    echo "  Person Name: " . ($eval->evaluatedPerson ? $eval->evaluatedPerson->name : 'N/A') . "\n";
    echo "  Form ID: {$eval->form_id}\n";
    echo "  Requests: {$eval->evaluationRequests->count()}\n";
    
    foreach ($eval->evaluationRequests as $req) {
        echo "    Request ID: {$req->id}, Requester: " . ($req->requester ? $req->requester->name : 'N/A') . ", Requested: " . ($req->requested ? $req->requested->name : 'N/A') . "\n";
    }
    echo "\n";
}

// Vamos criar um recurso de teste com uma pessoa que tem avaliação chefia
echo "Verificando se existe recurso para pessoas com avaliação chefia:\n";
foreach ($chefiaEvals as $eval) {
    $existingRecourse = \App\Models\EvaluationRecourse::whereHas('evaluation.evaluation', function($query) use ($eval) {
        $query->where('evaluated_person_id', $eval->evaluated_person_id);
    })->first();
    
    if ($existingRecourse) {
        echo "ENCONTRADO! Recurso ID: {$existingRecourse->id} para person_id: {$eval->evaluated_person_id}\n";
        break;
    }
}
