<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== DEBUG DETALHADO DO PROBLEMA ===\n";

$leonardo = \App\Models\Person::find(98); // Leonardo
$breno = \App\Models\Person::find(79); // Breno Muniz (chefe do Leonardo)

echo "LEONARDO:\n";
echo "ID: {$leonardo->id}, Nome: {$leonardo->name}\n";
echo "Chefe: {$leonardo->directManager->name} (ID: {$leonardo->direct_manager_id})\n";
echo "Bond Type: {$leonardo->bond_type}\n";
echo "É Gestor: " . ($leonardo->jobFunction && $leonardo->jobFunction->is_manager ? 'SIM' : 'NÃO') . "\n";

echo "\nBRENU MUNIZ (Chefe do Leonardo):\n";
echo "ID: {$breno->id}, Nome: {$breno->name}\n";
echo "Chefe: " . ($breno->directManager ? $breno->directManager->name . " (ID: {$breno->direct_manager_id})" : 'NENHUM') . "\n";
echo "Bond Type: {$breno->bond_type}\n";
echo "É Gestor: " . ($breno->jobFunction && $breno->jobFunction->is_manager ? 'SIM' : 'NÃO') . "\n";

echo "\n=== ANALYSIS DO REQUEST PROBLEMÁTICO ===\n";
$problemRequest = \App\Models\EvaluationRequest::find(5135);
echo "Request ID: {$problemRequest->id}\n";
echo "Evaluation ID: {$problemRequest->evaluation_id}\n";
echo "Requested Person (quem vai avaliar): {$problemRequest->requested_person_id} - {$problemRequest->requested->name}\n";
echo "Requester Person (quem solicitou): {$problemRequest->requester_person_id} - {$problemRequest->requester->name}\n";

$evaluation = $problemRequest->evaluation;
echo "\nEvaluation:\n";
echo "ID: {$evaluation->id}\n";
echo "Type: {$evaluation->type}\n";
echo "Evaluated Person (quem está sendo avaliado): {$evaluation->evaluated_person_id} - {$evaluation->evaluatedPerson->name}\n";
echo "Form ID: {$evaluation->form_id}\n";

echo "\n=== PROBLEMA IDENTIFICADO ===\n";
if ($problemRequest->requested_person_id == $leonardo->id && 
    $evaluation->evaluated_person_id == $breno->id && 
    $evaluation->type == 'chefia') {
    echo "✓ CORRETO: Leonardo (ID: {$leonardo->id}) está avaliando seu chefe Breno (ID: {$breno->id})\n";
} else {
    echo "✗ PROBLEMA: Algo está errado na configuração\n";
}

echo "\n=== VERIFICAR OUTRAS AVALIAÇÕES DE CHEFIA DO BRENO ===\n";
$allChefiaRequestsForBreno = \App\Models\EvaluationRequest::where('evaluation_id', $evaluation->id)->get();
echo "Total de pessoas avaliando Breno como chefe: {$allChefiaRequestsForBreno->count()}\n";
foreach ($allChefiaRequestsForBreno as $req) {
    $avaliador = $req->requested;
    echo "- {$avaliador->name} (ID: {$avaliador->id})\n";
    
    // Verificar se o avaliador tem Breno como chefe
    if ($avaliador->direct_manager_id == $breno->id) {
        echo "  ✓ Tem Breno como chefe direto\n";
    } else {
        echo "  ✗ NÃO tem Breno como chefe direto (chefe: " . 
             ($avaliador->directManager ? $avaliador->directManager->name : 'NENHUM') . ")\n";
    }
}

echo "\n=== VERIFICAR LÓGICA DO DASHBOARD CONTROLLER ===\n";
// Reproduzir a lógica exata do DashboardController linha 171-175
$bossEvalRequest = \App\Models\EvaluationRequest::where('requested_person_id', $leonardo->id)
    ->whereHas('evaluation', function ($q) {
        $q->where('type', 'chefia');
    })
    ->first();

if ($bossEvalRequest) {
    echo "Dashboard encontrou request: ID {$bossEvalRequest->id}\n";
    echo "Pessoa sendo avaliada: {$bossEvalRequest->evaluation->evaluatedPerson->name}\n";
    
    // Verificar se é o mesmo problema
    if ($bossEvalRequest->id == 5135) {
        echo "É o mesmo request problemático\n";
        
        // O problema pode estar na interface Vue.js
        echo "\n=== DADOS QUE VÃO PARA O FRONTEND ===\n";
        echo "bossEvaluationRequestId: {$bossEvalRequest->id}\n";
        echo "bossEvaluationVisible: " . (!($bossEvalRequest && $bossEvalRequest->status === 'completed') ? 'true' : 'false') . "\n";
    }
}

echo "\n=== VERIFICAR SE LEONARDO PODE AVALIAR CHEFE ===\n";
// Verificar as regras do GenerateEvaluationsJob
$leonardoCanEvaluateChef = \App\Models\Person::where(function ($query) {
    $query->whereIn('functional_status', ['TRABALHANDO', 'FERIAS', 'CEDIDO', 'AFASTADO'])
        ->where(function ($subQuery) {
            // Exclui apenas "3 - Concursado" sem função
            $subQuery->where('bond_type', '!=', '3 - Concursado')
                ->orWhereNotNull('job_function_id');
        });
    })
    ->where('id', $leonardo->id)
    ->exists();

echo "Leonardo pode avaliar chefe (pelas regras): " . ($leonardoCanEvaluateChef ? 'SIM' : 'NÃO') . "\n";

echo "\n=== VERIFICAR SE BRENO PODE SER AVALIADO ===\n";
$brenoCanBeEvaluated = \App\Models\Person::where('bond_type', '!=', '8 - Concursado')
    ->where('id', $breno->id)
    ->exists();

echo "Breno pode ser avaliado (pelas regras): " . ($brenoCanBeEvaluated ? 'SIM' : 'NÃO') . "\n";
echo "Bond type do Breno: {$breno->bond_type}\n";
