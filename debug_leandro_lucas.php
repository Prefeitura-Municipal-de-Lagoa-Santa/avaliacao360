<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== DEBUG LEANDRO LUCAS DOMINGOS ===\n";

// Buscar Leandro Lucas Domingos
$leandro = \App\Models\Person::where('name', 'LIKE', '%LEANDRO%LUCAS%DOMINGOS%')
    ->orWhere('name', 'LIKE', '%LEANDRO LUCAS DOMINGOS%')
    ->first();

if (!$leandro) {
    echo "Leandro Lucas Domingos não encontrado. Buscando variações...\n";
    $leandro = \App\Models\Person::where('name', 'LIKE', '%LEANDRO%')
        ->where('name', 'LIKE', '%LUCAS%')
        ->where('name', 'LIKE', '%DOMINGOS%')
        ->first();
}

if ($leandro) {
    echo "DADOS DE LEANDRO LUCAS DOMINGOS:\n";
    echo "ID: {$leandro->id}\n";
    echo "Nome: {$leandro->name}\n";
    echo "CPF: {$leandro->cpf}\n";
    echo "Matrícula: {$leandro->registration_number}\n";
    echo "Status Funcional: {$leandro->functional_status}\n";
    echo "Tipo de Vínculo: {$leandro->bond_type}\n";
    echo "Chefe Direto ID: {$leandro->direct_manager_id}\n";
    
    if ($leandro->directManager) {
        echo "Chefe Direto: {$leandro->directManager->name} (ID: {$leandro->directManager->id})\n";
        
        // Verificar o chefe do chefe (que seria Breno Muniz)
        $chefeDoChefe = $leandro->directManager->directManager;
        if ($chefeDoChefe) {
            echo "Chefe do Chefe: {$chefeDoChefe->name} (ID: {$chefeDoChefe->id})\n";
        }
    } else {
        echo "Chefe Direto: NENHUM\n";
    }
    
    if ($leandro->jobFunction) {
        echo "Função: {$leandro->jobFunction->name}\n";
        echo "É Gestor: " . ($leandro->jobFunction->is_manager ? 'SIM' : 'NÃO') . "\n";
    } else {
        echo "Função: NENHUMA\n";
    }
    
    echo "\n=== VERIFICAR SE ESTÁ EM ESTÁGIO PROBATÓRIO ===\n";
    echo "Status funcional: {$leandro->functional_status}\n";
    echo "Tipo de vínculo: {$leandro->bond_type}\n";
    
    // Verificar se pode ser avaliado
    $podeSerAvaliado = \App\Models\Person::where('bond_type', '!=', '8 - Concursado')
        ->where('id', $leandro->id)
        ->exists();
    echo "Pode ser avaliado: " . ($podeSerAvaliado ? 'SIM' : 'NÃO') . "\n";
    
    // Verificar se pode avaliar chefe
    $podeAvaliarChefe = \App\Models\Person::where(function ($query) {
        $query->whereIn('functional_status', ['TRABALHANDO', 'FERIAS', 'CEDIDO', 'AFASTADO'])
            ->where(function ($subQuery) {
                $subQuery->where('bond_type', '!=', '3 - Concursado')
                    ->orWhereNotNull('job_function_id');
            });
        })
        ->where('id', $leandro->id)
        ->exists();
    echo "Pode avaliar chefe: " . ($podeAvaliarChefe ? 'SIM' : 'NÃO') . "\n";
    
    echo "\n=== AVALIAÇÕES DE CHEFIA ONDE LEANDRO É AVALIADOR ===\n";
    $avaliacoesChefia = \App\Models\EvaluationRequest::where('requested_person_id', $leandro->id)
        ->whereHas('evaluation', function ($q) {
            $q->where('type', 'chefia');
        })
        ->with(['evaluation.evaluatedPerson', 'evaluation.form'])
        ->get();
    
    if ($avaliacoesChefia->count() > 0) {
        foreach ($avaliacoesChefia as $req) {
            echo "Request ID: {$req->id}\n";
            echo "  Avaliando: {$req->evaluation->evaluatedPerson->name} (ID: {$req->evaluation->evaluated_person_id})\n";
            echo "  Status: {$req->status}\n";
            echo "  Evaluation ID: {$req->evaluation_id}\n";
            echo "  Tipo: {$req->evaluation->type}\n";
            echo "  Form ID: {$req->evaluation->form_id}\n";
            echo "  Year: {$req->evaluation->form->year}\n";
            
            // Verificar se é o chefe direto
            if ($req->evaluation->evaluated_person_id == $leandro->direct_manager_id) {
                echo "  -> Esta é a avaliação do CHEFE DIRETO ✓\n";
            } else {
                echo "  -> Esta NÃO é a avaliação do chefe direto ✗\n";
                echo "     Chefe direto: " . ($leandro->directManager ? $leandro->directManager->name : 'NENHUM') . "\n";
                
                // Verificar se é o chefe do chefe
                if ($leandro->directManager && $req->evaluation->evaluated_person_id == $leandro->directManager->direct_manager_id) {
                    echo "  -> Esta é a avaliação do CHEFE DO CHEFE! ⚠️\n";
                }
            }
            echo "\n";
        }
    } else {
        echo "Leandro Lucas não tem avaliações de chefia para fazer.\n";
    }
    
    echo "\n=== DIAGNÓSTICO DO PROBLEMA ===\n";
    
    // Se Leandro está em estágio probatório, não deveria ter avaliações
    if ($leandro->functional_status == 'ESTAGIO_PROBATORIO' || $leandro->bond_type == '8 - Concursado') {
        echo "PROBLEMA: Leandro está em situação que não deveria ter avaliações\n";
        echo "Status: {$leandro->functional_status}\n";
        echo "Bond Type: {$leandro->bond_type}\n";
        
        if ($avaliacoesChefia->count() > 0) {
            echo "MAS tem " . $avaliacoesChefia->count() . " avaliação(ões) de chefia!\n";
            echo "Isso indica um erro na geração das avaliações.\n";
        }
    }
    
    echo "\n=== VERIFICAR LÓGICA DO DASHBOARD ===\n";
    $bossEvalRequest = \App\Models\EvaluationRequest::where('requested_person_id', $leandro->id)
        ->whereHas('evaluation', function ($q) {
            $q->where('type', 'chefia');
        })
        ->first();
        
    if ($bossEvalRequest) {
        echo "Dashboard mostrará botão de avaliação de chefia\n";
        echo "Request ID: {$bossEvalRequest->id}\n";
        echo "Pessoa sendo avaliada: {$bossEvalRequest->evaluation->evaluatedPerson->name}\n";
        echo "Isso explica por que o botão aparece!\n";
    } else {
        echo "Dashboard NÃO mostrará botão de avaliação de chefia\n";
    }
    
} else {
    echo "Leandro Lucas Domingos não encontrado no banco de dados\n";
    
    // Listar alguns Leandros para ajudar
    echo "\nLeandros encontrados:\n";
    $leandros = \App\Models\Person::where('name', 'LIKE', '%LEANDRO%')->take(5)->get();
    foreach ($leandros as $l) {
        echo "- {$l->name} (ID: {$l->id})\n";
    }
}
