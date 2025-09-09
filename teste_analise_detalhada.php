<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Person;
use App\Models\Answer;
use App\Models\EvaluationRequest;

echo "=== ANÁLISE DETALHADA DA EVALUATION 5382 ===\n\n";

$evaluation = \App\Models\Evaluation::with(['evaluatedPerson', 'evaluationRequests.requestedPerson'])
    ->find(5382);

if (!$evaluation) {
    echo "Evaluation não encontrada!\n";
    exit;
}

$chefe = $evaluation->evaluatedPerson;
$requests = $evaluation->evaluationRequests;

echo "CHEFE: {$chefe->name}\n";
echo "Evaluation ID: {$evaluation->id}\n";
echo "Type: {$evaluation->type}\n\n";

echo "REQUESTS:\n";
foreach ($requests as $request) {
    $avaliador = $request->requestedPerson;
    echo "Request ID: {$request->id}\n";
    echo "  Avaliador: {$avaliador->name} (ID: {$avaliador->id})\n";
    echo "  Subject Person ID: {$request->requested_person_id}\n";
    echo "  Status: {$request->status}\n";
    echo "  Evidências: " . (empty($request->evidencias) ? 'NÃO' : 'SIM') . "\n";
    echo "  Assinatura: " . (empty($request->assinatura_base64) ? 'NÃO' : 'SIM') . "\n";
    echo "\n";
}

echo "TODAS AS RESPOSTAS:\n";
$todasRespostas = Answer::where('evaluation_id', $evaluation->id)->get();
echo "Total: {$todasRespostas->count()}\n\n";

foreach ($todasRespostas as $answer) {
    echo "Answer ID: {$answer->id}\n";
    echo "  Question ID: {$answer->question_id}\n";
    echo "  Score: {$answer->score}\n";
    echo "  Subject Person ID: {$answer->subject_person_id}\n";
    $person = Person::find($answer->subject_person_id);
    echo "  Subject Person: {$person?->name}\n";
    echo "\n";
}

echo "AGRUPADAS POR SUBJECT_PERSON_ID:\n";
$porAvaliador = $todasRespostas->groupBy('subject_person_id');
foreach ($porAvaliador as $subjectId => $respostas) {
    $person = Person::find($subjectId);
    echo "Subject ID {$subjectId} ({$person?->name}):\n";
    foreach ($respostas as $resposta) {
        echo "  Question {$resposta->question_id}: Score {$resposta->score}\n";
    }
    $scores = $respostas->pluck('score')->filter(function($score) { return $score !== null; })->all();
    $media = count($scores) > 0 ? round(array_sum($scores) / count($scores), 2) : 0;
    echo "  Média: {$media}\n";
    echo "\n";
}

echo "=== FIM DA ANÁLISE ===\n";
