<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== DEBUG USUÁRIO LOGADO ===\n";

// Simular a autenticação (vamos ver quem está logado)
echo "Verificando usuários cadastrados:\n";

$users = \App\Models\User::all();
foreach ($users as $user) {
    echo "User ID: {$user->id}, Nome: {$user->name}, CPF: {$user->cpf}\n";
    
    if (str_contains(strtolower($user->name), 'leandro') || str_contains(strtolower($user->name), 'leonardo')) {
        echo "  -> Este pode ser o usuário em questão!\n";
        
        $person = \App\Models\Person::where('cpf', $user->cpf)->first();
        if ($person) {
            echo "  -> Person encontrada: {$person->name} (ID: {$person->id})\n";
            echo "  -> Função: " . ($person->jobFunction ? $person->jobFunction->name : 'NENHUMA') . "\n";
            echo "  -> É Gestor: " . ($person->jobFunction && $person->jobFunction->is_manager ? 'SIM' : 'NÃO') . "\n";
            echo "  -> Subordinados: " . \App\Models\Person::where('direct_manager_id', $person->id)->count() . "\n";
        } else {
            echo "  -> ERRO: Person não encontrada para este CPF!\n";
        }
        echo "\n";
    }
}

echo "\n=== VERIFICANDO PROBLEMA ESPECÍFICO ===\n";

// Verificar se há algum problema na lógica de exibição
$leandroUser = \App\Models\User::where('name', 'LIKE', '%LEANDRO%')->first();
if ($leandroUser) {
    echo "Usuário Leandro encontrado: {$leandroUser->name} (CPF: {$leandroUser->cpf})\n";
    
    $person = \App\Models\Person::where('cpf', $leandroUser->cpf)->first();
    if ($person) {
        echo "Person: {$person->name} (ID: {$person->id})\n";
        
        // Verificar a lógica do dashboard para este usuário
        $currentYear = date('Y');
        $now = now();
        
        // Buscar pendingTeamRequests
        $pendingTeamRequests = \App\Models\EvaluationRequest::where('requester_person_id', $person->id)
            ->whereHas('evaluation', function ($q) {
                $q->whereIn('type', ['servidor', 'gestor', 'comissionado']);
            })
            ->where('status', 'pending')
            ->get();
        
        echo "Pending team requests: {$pendingTeamRequests->count()}\n";
        
        if ($pendingTeamRequests->count() > 0) {
            echo "ATENÇÃO: Usuário tem pending team requests!\n";
            foreach ($pendingTeamRequests as $req) {
                echo "  - Request ID: {$req->id}\n";
                echo "    Evaluation ID: {$req->evaluation_id}\n";
                echo "    Requested Person: {$req->requested_person_id}\n";
                echo "    Status: {$req->status}\n";
                echo "    Type: {$req->evaluation->type}\n";
            }
        }
        
        // Verificar se existe form liberado
        $selfForm = \App\Models\Form::where('year', $currentYear)
            ->whereIn('type', ['servidor', 'gestor', 'comissionado'])
            ->where('release', true)
            ->first();
        
        if ($selfForm) {
            echo "Form encontrado e liberado: ID {$selfForm->id}, Type: {$selfForm->type}\n";
            echo "Prazo: {$selfForm->term_first} até {$selfForm->term_end}\n";
            
            $isWithinSelfStandardPeriod = $selfForm->term_first && $selfForm->term_end && 
                $now->between(
                    \Carbon\Carbon::parse($selfForm->term_first)->startOfDay(), 
                    \Carbon\Carbon::parse($selfForm->term_end)->endOfDay()
                );
            
            echo "Dentro do prazo: " . ($isWithinSelfStandardPeriod ? 'SIM' : 'NÃO') . "\n";
            
            if ($isWithinSelfStandardPeriod && $pendingTeamRequests->count() > 0) {
                echo "*** PROBLEMA IDENTIFICADO: Usuário está vendo botão de Avaliar Equipe! ***\n";
            }
        }
    }
}
