<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Person;
use App\Models\Answer;
use App\Models\EvaluationRequest;

echo "=== TESTE DA EXIBIÇÃO DE RESULTADOS ===\n\n";

// Usar a evaluation 5382 que sabemos que tem dados
$requests = \App\Models\EvaluationRequest::where('evaluation_id', 5382)
    ->where('status', 'completed')
    ->with(['requestedPerson', 'evaluation'])
    ->get();

foreach ($requests as $request) {
    $person = $request->requestedPerson;
    echo "AVALIADOR: {$person->name} (ID: {$person->id})\n";
    echo "Request ID: {$request->id}\n";
    echo "Subject Person ID: {$request->requested_person_id}\n";
    
    // Testar como nossa correção vai buscar as respostas
    $answers = Answer::where('evaluation_id', $request->evaluation_id)
        ->where('subject_person_id', $request->requested_person_id)
        ->get();
    
    echo "Respostas encontradas: {$answers->count()}\n";
    
    if ($answers->count() > 0) {
        $scores = $answers->pluck('score')->filter(function($score) { return $score !== null; })->all();
        $total = array_sum($scores);
        $media = round($total / count($scores), 2);
        
        echo "Scores: " . implode(', ', $scores) . "\n";
        echo "Total: {$total}\n";
        echo "Média: {$media}\n";
        
        // Simular como seria exibido na tela
        echo "RESULTADO ESPERADO NA TELA:\n";
        echo "  Nome: POLLIANA MOURA RIBEIRO DE ABREU (avaliada)\n";
        echo "  Avaliador: {$person->name}\n";
        echo "  Pontuação Total: {$total}\n";
        
        echo "  Questões:\n";
        foreach ($answers as $answer) {
            echo "    Questão {$answer->question_id}: {$answer->score}\n";
        }
    } else {
        echo "NENHUMA RESPOSTA - PROBLEMA PERSISTENTE!\n";
    }
    
    echo "\n" . str_repeat('-', 50) . "\n\n";
}

echo "=== VERIFICAÇÃO DO BEFORE/AFTER ===\n\n";

// Verificar se há duplicação de respostas
$todasRespostas = Answer::where('evaluation_id', 5382)->get();
$porSubject = $todasRespostas->groupBy('subject_person_id');

echo "Total de respostas na evaluation_id 5382: {$todasRespostas->count()}\n";
echo "Distribuição por subject_person_id:\n";

foreach ($porSubject as $subjectId => $respostas) {
    $person = Person::find($subjectId);
    echo "  {$person?->name} (ID: {$subjectId}): {$respostas->count()} respostas\n";
    $scores = $respostas->pluck('score')->filter()->all();
    echo "    Scores: " . implode(', ', $scores) . "\n";
}

echo "\n=== FIM DO TESTE ===\n";
