<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== VERIFICAR SE HÁ AVALIAÇÕES FANTASMAS ===\n";

$leandro = \App\Models\Person::find(110); // Leandro Lucas Domingos

echo "VERIFICANDO TODAS AS REQUESTS PARA LEANDRO:\n";
$todasRequests = \App\Models\EvaluationRequest::where('requested_person_id', $leandro->id)
    ->with(['evaluation.evaluatedPerson', 'evaluation.form'])
    ->get();

echo "Total: {$todasRequests->count()}\n\n";

foreach ($todasRequests as $req) {
    echo "Request ID: {$req->id}\n";
    echo "  Evaluation ID: {$req->evaluation_id}\n";
    echo "  Tipo: {$req->evaluation->type}\n";
    echo "  Avaliando: {$req->evaluation->evaluatedPerson->name} (ID: {$req->evaluation->evaluated_person_id})\n";
    echo "  Status: {$req->status}\n";
    echo "  Form: " . ($req->evaluation->form ? "ID {$req->evaluation->form_id} - Year {$req->evaluation->form->year}" : 'NULL') . "\n";
    echo "  Created: {$req->created_at}\n";
    echo "  Updated: {$req->updated_at}\n\n";
}

echo "=== VERIFICAR AVALIAÇÕES TIPO CHEFIA EM GERAL ===\n";
$chefiaRequests = \App\Models\EvaluationRequest::whereHas('evaluation', function ($q) {
    $q->where('type', 'chefia');
})
->where('requested_person_id', $leandro->id)
->with(['evaluation.evaluatedPerson'])
->get();

if ($chefiaRequests->count() > 0) {
    echo "PROBLEMA ENCONTRADO! Leandro tem {$chefiaRequests->count()} request(s) de chefia:\n";
    foreach ($chefiaRequests as $req) {
        echo "- Request ID: {$req->id}\n";
        echo "  Avaliando: {$req->evaluation->evaluatedPerson->name}\n";
        echo "  Status: {$req->status}\n";
    }
} else {
    echo "✓ Leandro não tem requests de chefia (correto)\n";
}

echo "\n=== VERIFICAR POR CPF (possível problema de autenticação) ===\n";
$userCpf = '15570391606'; // CPF do Leandro
$requestsPorCpf = \App\Models\EvaluationRequest::whereHas('requested', function ($q) use ($userCpf) {
    $q->where('cpf', $userCpf);
})
->whereHas('evaluation', function ($q) {
    $q->where('type', 'chefia');
})
->with(['evaluation.evaluatedPerson', 'requested'])
->get();

if ($requestsPorCpf->count() > 0) {
    echo "REQUESTS DE CHEFIA POR CPF:\n";
    foreach ($requestsPorCpf as $req) {
        echo "- Request ID: {$req->id}\n";
        echo "  Requested Person: {$req->requested->name} (ID: {$req->requested_person_id})\n";
        echo "  Avaliando: {$req->evaluation->evaluatedPerson->name}\n";
        echo "  Status: {$req->status}\n";
    }
} else {
    echo "✓ Não há requests de chefia para este CPF\n";
}

echo "\n=== VERIFICAR SIMULAÇÃO DO DASHBOARD ===\n";
// Simular exatamente o que o DashboardController faz
$person = \App\Models\Person::where('cpf', $userCpf)->first();
if ($person) {
    echo "Person encontrada: {$person->name} (ID: {$person->id})\n";
    
    $bossEvalRequest = \App\Models\EvaluationRequest::where('requested_person_id', $person->id)
        ->whereHas('evaluation', function ($q) {
            $q->where('type', 'chefia');
        })
        ->first();
        
    if ($bossEvalRequest) {
        echo "Dashboard encontraria request: ID {$bossEvalRequest->id}\n";
        echo "Pessoa sendo avaliada: {$bossEvalRequest->evaluation->evaluatedPerson->name}\n";
        echo "Status: {$bossEvalRequest->status}\n";
        
        $bossEvaluationCompleted = $bossEvalRequest && $bossEvalRequest->status === 'completed';
        $bossEvaluationVisible = !$bossEvaluationCompleted;
        
        echo "bossEvaluationVisible: " . ($bossEvaluationVisible ? 'true' : 'false') . "\n";
        echo "bossEvaluationRequestId: {$bossEvalRequest->id}\n";
    } else {
        echo "✓ Dashboard não encontraria request de chefia\n";
        echo "bossEvaluationVisible: false\n";
    }
} else {
    echo "Person não encontrada por CPF\n";
}
