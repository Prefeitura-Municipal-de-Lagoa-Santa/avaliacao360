<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Person;
use App\Models\Answer;
use App\Models\EvaluationRequest;

echo "=== TESTE DE NOTAS DE EQUIPE COM DADOS ===\n\n";

// Buscar evaluations que tenham respostas
$evaluationsComRespostas = \App\Models\Evaluation::whereHas('answers')
    ->where('type', 'chefia')
    ->with(['evaluatedPerson', 'evaluationRequests.requestedPerson'])
    ->get();

echo "Encontradas {$evaluationsComRespostas->count()} avaliações de chefia com respostas\n\n";

foreach ($evaluationsComRespostas->take(3) as $evaluation) {
    $chefe = $evaluation->evaluatedPerson;
    $requests = $evaluation->evaluationRequests;
    
    echo "CHEFE: {$chefe->name}\n";
    echo "Evaluation ID: {$evaluation->id}\n";
    echo "Requests ({$requests->count()}):\n";
    
    // Verificar todas as respostas desta evaluation_id
    $todasRespostas = Answer::where('evaluation_id', $evaluation->id)->get();
    echo "Total de respostas na evaluation_id {$evaluation->id}: {$todasRespostas->count()}\n";
    
    // Agrupar por subject_person_id
    $porAvaliador = $todasRespostas->groupBy('subject_person_id');
    echo "Avaliadores com respostas: " . $porAvaliador->count() . "\n";
    
    foreach ($porAvaliador as $subjectId => $respostas) {
        $person = Person::find($subjectId);
        $scores = $respostas->pluck('score')->filter()->all();
        $media = count($scores) > 0 ? round(array_sum($scores) / count($scores), 2) : 0;
        echo "  Subject ID {$subjectId} ({$person?->name}): {$respostas->count()} respostas | Scores: " . implode(', ', $scores) . " | Média: {$media}\n";
    }
    
    // Verificar quais requests estão completed
    $completedRequests = $requests->where('status', 'completed');
    echo "Requests completed: {$completedRequests->count()}\n";
    
    foreach ($completedRequests as $request) {
        $avaliador = $request->requestedPerson;
        echo "  - {$avaliador->name} (Subject ID: {$request->requested_person_id})\n";
    }
    
    echo "\n" . str_repeat('-', 60) . "\n\n";
}

echo "=== FIM DO TESTE ===\n";
