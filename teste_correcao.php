<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Person;
use App\Models\Answer;
use App\Models\EvaluationRequest;
use App\Models\Evaluation;

echo "=== TESTE DE CORREÇÃO - NOTAS DE EQUIPE ===\n\n";

// Vamos usar a evaluation 5382 para teste
$evaluation = Evaluation::find(5382);
$requests = $evaluation->evaluationRequests;

echo "ANTES DA SIMULAÇÃO:\n";
$todasRespostas = Answer::where('evaluation_id', 5382)->get();
echo "Total de respostas: {$todasRespostas->count()}\n";

$porAvaliador = $todasRespostas->groupBy('subject_person_id');
foreach ($porAvaliador as $subjectId => $respostas) {
    $person = Person::find($subjectId);
    $scores = $respostas->pluck('score')->filter(function($score) { return $score !== null; })->all();
    $media = count($scores) > 0 ? round(array_sum($scores) / count($scores), 2) : 0;
    echo "  {$person?->name}: {$respostas->count()} respostas | Média: {$media}\n";
}

echo "\nSimulando salvamento da MÁRCIA (que perdeu as respostas):\n";

// Simular que MÁRCIA está salvando uma avaliação
$marciaRequest = $requests->where('requested_person_id', 1978)->first();

if ($marciaRequest) {
    echo "Request da Márcia encontrado (ID: {$marciaRequest->id})\n";
    
    // Simular dados de uma avaliação com notas altas
    $answerData = [
        ['question_id' => 21, 'score' => 100],
        ['question_id' => 22, 'score' => 95],
        ['question_id' => 23, 'score' => 90],
        ['question_id' => 24, 'score' => 100],
        ['question_id' => 25, 'score' => 85],
    ];
    
    echo "Deletando respostas antigas da Márcia (subject_person_id: {$marciaRequest->requested_person_id})...\n";
    
    // Aplicar nossa correção: deletar apenas as respostas específicas dela
    $deletadas = Answer::where('evaluation_id', $evaluation->id)
        ->where('subject_person_id', $marciaRequest->requested_person_id)
        ->delete();
    
    echo "Respostas deletadas: {$deletadas}\n";
    
    echo "Criando novas respostas para a Márcia...\n";
    
    // Criar novas respostas
    foreach ($answerData as $answer) {
        Answer::create([
            'question_id' => $answer['question_id'],
            'evaluation_id' => $evaluation->id,
            'score' => $answer['score'],
            'subject_person_id' => $marciaRequest->requested_person_id,
        ]);
    }
    
    echo "Novas respostas criadas!\n";
    
    // Atualizar status se necessário
    if ($marciaRequest->status !== 'completed') {
        $marciaRequest->update(['status' => 'completed']);
        echo "Status da request atualizado para completed\n";
    }
}

echo "\nAPÓS A SIMULAÇÃO:\n";
$todasRespostasDepois = Answer::where('evaluation_id', 5382)->get();
echo "Total de respostas: {$todasRespostasDepois->count()}\n";

$porAvaliadorDepois = $todasRespostasDepois->groupBy('subject_person_id');
foreach ($porAvaliadorDepois as $subjectId => $respostas) {
    $person = Person::find($subjectId);
    $scores = $respostas->pluck('score')->filter(function($score) { return $score !== null; })->all();
    $media = count($scores) > 0 ? round(array_sum($scores) / count($scores), 2) : 0;
    echo "  {$person?->name}: {$respostas->count()} respostas | Scores: " . implode(', ', $scores) . " | Média: {$media}\n";
}

echo "\nTESTE DE CÁLCULO DA MÉDIA DA EQUIPE:\n";
$completedRequests = EvaluationRequest::where('evaluation_id', 5382)
    ->where('status', 'completed')
    ->get();

$mediasDosAvaliadores = [];
foreach ($completedRequests as $request) {
    $answers = Answer::where('evaluation_id', $request->evaluation_id)
        ->where('subject_person_id', $request->requested_person_id)
        ->get();
    
    $scores = $answers->pluck('score')->filter(function($score) { return $score !== null; })->all();
    $media = count($scores) > 0 ? round(array_sum($scores) / count($scores), 2) : 0;
    
    $person = Person::find($request->requested_person_id);
    echo "  {$person?->name}: Média individual {$media}\n";
    $mediasDosAvaliadores[] = $media;
}

$mediaGeral = count($mediasDosAvaliadores) > 0 ? round(array_sum($mediasDosAvaliadores) / count($mediasDosAvaliadores), 2) : 0;
echo "\nMÉDIA GERAL DA EQUIPE: {$mediaGeral}\n";

echo "\n=== FIM DO TESTE ===\n";
