<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Person;
use App\Models\Answer;
use App\Models\EvaluationRequest;

echo "=== TESTE DE NOTAS DE EQUIPE ===\n\n";

// Buscar uma pessoa que seja avaliada por equipe (type = 'chefia')
$chefesAvaliados = \App\Models\Evaluation::where('type', 'chefia')
    ->with(['evaluatedPerson', 'evaluationRequests.requestedPerson'])
    ->get();

foreach ($chefesAvaliados as $evaluation) {
    $chefe = $evaluation->evaluatedPerson;
    $requests = $evaluation->evaluationRequests;
    
    if ($requests->count() > 1) { // Só mostrar se tem mais de 1 avaliador
        echo "CHEFE: {$chefe->name}\n";
        echo "Evaluation ID: {$evaluation->id}\n";
        echo "Avaliadores ({$requests->count()}):\n";
        
        foreach ($requests as $request) {
            $avaliador = $request->requestedPerson;
            echo "  - {$avaliador->name} (ID: {$avaliador->id}) - Status: {$request->status}\n";
            
            // Buscar respostas específicas deste avaliador
            $answers = Answer::where('evaluation_id', $evaluation->id)
                ->where('subject_person_id', $request->requested_person_id)
                ->get();
            
            if ($answers->count() > 0) {
                $scores = $answers->pluck('score')->filter()->all();
                $media = count($scores) > 0 ? round(array_sum($scores) / count($scores), 2) : 0;
                echo "    Respostas: " . count($answers) . " | Média: {$media}\n";
                echo "    Scores: " . implode(', ', $scores) . "\n";
            } else {
                echo "    Sem respostas encontradas\n";
            }
        }
        
        // Verificar todas as respostas desta evaluation_id
        $todasRespostas = Answer::where('evaluation_id', $evaluation->id)->get();
        echo "  Total de respostas na evaluation_id {$evaluation->id}: {$todasRespostas->count()}\n";
        
        // Agrupar por subject_person_id
        $porAvaliador = $todasRespostas->groupBy('subject_person_id');
        echo "  Avaliadores com respostas: " . $porAvaliador->count() . "\n";
        
        foreach ($porAvaliador as $subjectId => $respostas) {
            $person = Person::find($subjectId);
            $scores = $respostas->pluck('score')->filter()->all();
            $media = count($scores) > 0 ? round(array_sum($scores) / count($scores), 2) : 0;
            echo "    Subject ID {$subjectId} ({$person?->name}): {$respostas->count()} respostas | Média: {$media}\n";
        }
        
        echo "\n" . str_repeat('-', 60) . "\n\n";
        break; // Mostrar apenas o primeiro para não poluir muito
    }
}

echo "=== FIM DO TESTE ===\n";
