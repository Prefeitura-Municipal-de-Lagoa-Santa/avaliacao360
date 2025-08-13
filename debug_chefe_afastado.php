<?php

require 'vendor/autoload.php';
require 'bootstrap/app.php';

use App\Models\Person;

// Buscar pessoas afastadas que são chefes
$chefesAfastados = Person::where('functional_status', 'AFASTADO')
    ->whereNotNull('job_function_id')
    ->with(['directManager', 'subordinates'])
    ->get();

echo "=== CHEFES AFASTADOS ===" . PHP_EOL;
foreach ($chefesAfastados as $chefe) {
    echo "Chefe: {$chefe->name} (ID: {$chefe->id}) - Status: {$chefe->functional_status}" . PHP_EOL;
    echo "  Chefe do chefe: " . ($chefe->directManager ? "{$chefe->directManager->name} (ID: {$chefe->directManager->id}) - Status: {$chefe->directManager->functional_status}" : 'Nenhum') . PHP_EOL;
    echo "  Subordinados: {$chefe->subordinates->count()}" . PHP_EOL;
    foreach ($chefe->subordinates as $sub) {
        echo "    - {$sub->name} (ID: {$sub->id}) - Status: {$sub->functional_status}" . PHP_EOL;
    }
    echo PHP_EOL;
}

// Verificar se pessoas podem avaliar
echo "=== VERIFICAÇÃO DE QUEM PODE AVALIAR ===" . PHP_EOL;
$pessoasQuePodemAvaliar = Person::eligibleForEvaluation()
    ->where('functional_status', '!=', 'AFASTADO')
    ->where(function ($query) {
        $query->where('bond_type', '!=', '8 - Concursado')
              ->orWhereNotNull('job_function_id');
    })->get();

echo "Pessoas que podem avaliar: {$pessoasQuePodemAvaliar->count()}" . PHP_EOL;
foreach ($pessoasQuePodemAvaliar->take(5) as $pessoa) {
    echo "  - {$pessoa->name} (ID: {$pessoa->id}) - Status: {$pessoa->functional_status} - Bond: {$pessoa->bond_type}" . PHP_EOL;
}
