<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== DEBUG COMPLETO DA SITUAÇÃO ===\n";

$leandro = \App\Models\Person::find(110); // Leandro Lucas Domingos
$leonardo = \App\Models\Person::find(98); // Leonardo Campos (chefe do Leandro)
$breno = \App\Models\Person::find(79); // Breno Muniz (chefe do Leonardo)

echo "HIERARQUIA:\n";
echo "Breno Muniz (ID: 79) -> Leonardo Campos (ID: 98) -> Leandro Lucas (ID: 110)\n\n";

echo "LEANDRO LUCAS DOMINGOS:\n";
echo "ID: {$leandro->id}, Nome: {$leandro->name}\n";
echo "CPF: {$leandro->cpf}\n";
echo "Status: {$leandro->functional_status}, Vínculo: {$leandro->bond_type}\n";
echo "Chefe: {$leandro->directManager->name} (ID: {$leandro->direct_manager_id})\n";

echo "\nLEONARDO CAMPOS (Chefe do Leandro):\n";
echo "ID: {$leonardo->id}, Nome: {$leonardo->name}\n";
echo "CPF: {$leonardo->cpf}\n";
echo "Status: {$leonardo->functional_status}, Vínculo: {$leonardo->bond_type}\n";
echo "Chefe: {$leonardo->directManager->name} (ID: {$leonardo->direct_manager_id})\n";
echo "É Gestor: " . ($leonardo->jobFunction && $leonardo->jobFunction->is_manager ? 'SIM' : 'NÃO') . "\n";

echo "\n=== VERIFICAR SE LEONARDO ESTÁ EM ESTÁGIO PROBATÓRIO ===\n";
echo "Status do Leonardo: {$leonardo->functional_status}\n";
echo "Vínculo do Leonardo: {$leonardo->bond_type}\n";

// Verificar se Leonardo pode ser avaliado
$leonardoPodeSerAvaliado = \App\Models\Person::where('bond_type', '!=', '8 - Concursado')
    ->where('id', $leonardo->id)
    ->exists();
echo "Leonardo pode ser avaliado: " . ($leonardoPodeSerAvaliado ? 'SIM' : 'NÃO') . "\n";

if ($leonardo->bond_type == '8 - Concursado') {
    echo "PROBLEMA: Leonardo é '8 - Concursado' e está em estágio probatório!\n";
    echo "Ele NÃO deveria receber avaliações de chefia.\n";
}

echo "\n=== VERIFICAR AVALIAÇÕES DE LEONARDO COMO CHEFE ===\n";
$avaliacoesDoLeonardo = \App\Models\Evaluation::where('evaluated_person_id', $leonardo->id)
    ->where('type', 'chefia')
    ->with(['evaluationRequests.requested'])
    ->get();

if ($avaliacoesDoLeonardo->count() > 0) {
    foreach ($avaliacoesDoLeonardo as $eval) {
        echo "Evaluation ID: {$eval->id} (Leonardo sendo avaliado como chefe)\n";
        echo "Form: {$eval->form_id}, Year: " . ($eval->form ? $eval->form->year : 'N/A') . "\n";
        echo "Requests: {$eval->evaluationRequests->count()}\n";
        
        foreach ($eval->evaluationRequests as $req) {
            echo "  - {$req->requested->name} (ID: {$req->requested_person_id}) - Status: {$req->status}\n";
            
            if ($req->requested_person_id == $leandro->id) {
                echo "    *** AQUI ESTÁ O PROBLEMA! Leandro tem request para avaliar Leonardo ***\n";
            }
        }
    }
} else {
    echo "Leonardo NÃO tem avaliações como chefe\n";
}

echo "\n=== VERIFICAR TODAS AS REQUESTS DE AVALIAÇÃO DE CHEFIA DO LEANDRO ===\n";
$todasRequests = \App\Models\EvaluationRequest::where('requested_person_id', $leandro->id)
    ->with(['evaluation.evaluatedPerson', 'evaluation.form'])
    ->get();

echo "Total de requests para Leandro: {$todasRequests->count()}\n";
foreach ($todasRequests as $req) {
    echo "Request ID: {$req->id}\n";
    echo "  Tipo: {$req->evaluation->type}\n";
    echo "  Avaliando: {$req->evaluation->evaluatedPerson->name}\n";
    echo "  Status: {$req->status}\n";
    echo "  Form Year: " . ($req->evaluation->form ? $req->evaluation->form->year : 'N/A') . "\n";
    
    if ($req->evaluation->type == 'chefia') {
        echo "  *** ESTA É UMA AVALIAÇÃO DE CHEFIA! ***\n";
        
        if ($req->evaluation->evaluated_person_id == $leonardo->id) {
            echo "  *** Leandro avaliando Leonardo como chefe ***\n";
        } elseif ($req->evaluation->evaluated_person_id == $breno->id) {
            echo "  *** Leandro avaliando Breno como chefe (problema!) ***\n";
        }
    }
    echo "\n";
}

echo "\n=== VERIFICAR GERAÇÃO DE AVALIAÇÕES ===\n";
// Simular a lógica do GenerateEvaluationsJob
echo "Verificando regras de geração...\n";

// Verificar se Leandro pode avaliar chefe
$leandroCanEvaluateChef = \App\Models\Person::where(function ($query) {
    $query->whereIn('functional_status', ['TRABALHANDO', 'FERIAS', 'CEDIDO', 'AFASTADO'])
        ->where(function ($subQuery) {
            $subQuery->where('bond_type', '!=', '3 - Concursado')
                ->orWhereNotNull('job_function_id');
        });
})
->where('id', $leandro->id)
->exists();

echo "Leandro pode avaliar chefe (regras): " . ($leandroCanEvaluateChef ? 'SIM' : 'NÃO') . "\n";

// Verificar se Leonardo pode ser avaliado como chefe
$leonardoCanBeEvaluatedAsChef = \App\Models\Person::where('bond_type', '!=', '8 - Concursado')
    ->where('id', $leonardo->id)
    ->exists();

echo "Leonardo pode ser avaliado como chefe (regras): " . ($leonardoCanBeEvaluatedAsChef ? 'SIM' : 'NÃO') . "\n";

if (!$leonardoCanBeEvaluatedAsChef) {
    echo "PROBLEMA IDENTIFICADO: Leonardo não pode ser avaliado, mas tem avaliações!\n";
}
