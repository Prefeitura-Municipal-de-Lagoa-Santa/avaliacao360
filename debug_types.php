<?php

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->boot();

use App\Models\Evaluation;
use App\Models\EvaluationRequest;

// Verifica tipos de avaliação
echo "Tipos de avaliação:\n";
$types = Evaluation::select('type')->distinct()->pluck('type')->toArray();
foreach ($types as $type) {
    $count = Evaluation::where('type', $type)->count();
    echo "- $type: $count avaliações\n";
}

// Verifica se há avaliações tipo chefia
echo "\nAvaliações tipo 'chefia':\n";
$chefiaEvaluations = Evaluation::where('type', 'chefia')->with(['evaluationRequests'])->get();
foreach ($chefiaEvaluations as $eval) {
    echo "- ID: {$eval->id}, Requests: {$eval->evaluationRequests->count()}\n";
}

// Verifica avaliações de uma pessoa específica se existir recurso
echo "\nVerificando recursos...\n";
$recourse = \App\Models\EvaluationRecourse::first();
if ($recourse && $recourse->evaluation) {
    echo "Recurso ID: {$recourse->id}\n";
    $personId = $recourse->evaluation->evaluation->evaluated_person_id;
    echo "Person ID avaliada: $personId\n";
    
    $evaluations = Evaluation::where('evaluated_person_id', $personId)->get();
    foreach ($evaluations as $eval) {
        echo "- Evaluation ID: {$eval->id}, Type: {$eval->type}, Requests: " . $eval->evaluationRequests->count() . "\n";
    }
}
