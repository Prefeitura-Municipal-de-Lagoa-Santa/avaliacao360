<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== TESTE DA LÓGICA DE GERAÇÃO ===\n";

$leandro = \App\Models\Person::find(110); // Leandro Lucas Domingos
$leonardo = \App\Models\Person::find(98); // Leonardo Campos (chefe do Leandro)

echo "TESTANDO LÓGICA DO GenerateEvaluationsJob:\n\n";

// Simular a função pessoasElegiveis()
echo "1. Verificando pessoasElegiveis():\n";
$pessoasElegiveis = \App\Models\Person::where('functional_status', '!=', 'DEMITIDO')
    ->where('functional_status', '!=', 'APOSENTADO')
    ->where('bond_type', '!=', '8 - Concursado');

$leonardoElegivel = $pessoasElegiveis->where('id', $leonardo->id)->exists();
echo "Leonardo é elegível: " . ($leonardoElegivel ? 'SIM' : 'NÃO') . "\n";
echo "Bond type do Leonardo: {$leonardo->bond_type}\n\n";

// Simular a função pessoasQuePodemAvaliarChefe()
echo "2. Verificando pessoasQuePodemAvaliarChefe():\n";
$pessoasQuePodemAvaliarChefe = \App\Models\Person::where(function ($query) {
    $query->whereIn('functional_status', ['TRABALHANDO', 'FERIAS', 'CEDIDO', 'AFASTADO'])
        ->where(function ($subQuery) {
            $subQuery->where('bond_type', '!=', '3 - Concursado')
                ->orWhereNotNull('job_function_id');
        });
});

$leandroPodeAvaliarChefe = $pessoasQuePodemAvaliarChefe->where('id', $leandro->id)->exists();
echo "Leandro pode avaliar chefe: " . ($leandroPodeAvaliarChefe ? 'SIM' : 'NÃO') . "\n";
echo "Status do Leandro: {$leandro->functional_status}\n";
echo "Bond type do Leandro: {$leandro->bond_type}\n\n";

// Simular a lógica completa
echo "3. Simulando lógica completa:\n";
$peopleWhoCanEvaluateChef = \App\Models\Person::where(function ($query) {
    $query->whereIn('functional_status', ['TRABALHANDO', 'FERIAS', 'CEDIDO', 'AFASTADO'])
        ->where(function ($subQuery) {
            $subQuery->where('bond_type', '!=', '3 - Concursado')
                ->orWhereNotNull('job_function_id');
        });
})
->whereNotNull('direct_manager_id')
->where('id', $leandro->id)
->first();

if ($peopleWhoCanEvaluateChef) {
    echo "Leandro está na lista de pessoas que podem avaliar chefe\n";
    
    $manager = $peopleWhoCanEvaluateChef->directManager;
    echo "Chefe do Leandro: {$manager->name} (ID: {$manager->id})\n";
    
    // Verificar se o chefe pode ser avaliado
    $managerCanBeEvaluated = \App\Models\Person::where('functional_status', '!=', 'DEMITIDO')
        ->where('functional_status', '!=', 'APOSENTADO')
        ->where('bond_type', '!=', '8 - Concursado')
        ->where('id', $manager->id)
        ->exists();
        
    echo "Chefe pode ser avaliado: " . ($managerCanBeEvaluated ? 'SIM' : 'NÃO') . "\n";
    
    if (!$managerCanBeEvaluated) {
        echo "✓ CORRETO: Sistema deveria pular criação da avaliação\n";
    } else {
        echo "✗ PROBLEMA: Sistema criaria avaliação incorretamente\n";
    }
} else {
    echo "Leandro NÃO está na lista de pessoas que podem avaliar chefe\n";
}

echo "\n=== VERIFICAR AVALIAÇÕES EXISTENTES ===\n";
// Verificar se existem avaliações de chefia para Leonardo
$avaliacoesLeonardo = \App\Models\Evaluation::where('evaluated_person_id', $leonardo->id)
    ->where('type', 'chefia')
    ->with(['evaluationRequests'])
    ->get();

if ($avaliacoesLeonardo->count() > 0) {
    echo "PROBLEMA: Existem {$avaliacoesLeonardo->count()} avaliações de chefia para Leonardo!\n";
    foreach ($avaliacoesLeonardo as $eval) {
        echo "Evaluation ID: {$eval->id}\n";
        echo "Requests: {$eval->evaluationRequests->count()}\n";
        foreach ($eval->evaluationRequests as $req) {
            echo "  - {$req->requested->name} avaliando Leonardo\n";
        }
    }
    
    echo "\nEssas avaliações foram criadas incorretamente e devem ser removidas.\n";
} else {
    echo "✓ Não existem avaliações de chefia para Leonardo (correto)\n";
}

echo "\n=== VERIFICAR USERS E LOGIN ===\n";
// Pode ser que o problema seja de usuário logado incorreto
$userLeandro = \App\Models\User::where('cpf', $leandro->cpf)->first();
if ($userLeandro) {
    echo "User do Leandro encontrado: {$userLeandro->name}\n";
    echo "CPF: {$userLeandro->cpf}\n";
} else {
    echo "User do Leandro não encontrado\n";
}

$userLeonardo = \App\Models\User::where('cpf', $leonardo->cpf)->first();
if ($userLeonardo) {
    echo "User do Leonardo encontrado: {$userLeonardo->name}\n";
    echo "CPF: {$userLeonardo->cpf}\n";
} else {
    echo "User do Leonardo não encontrado\n";
}
