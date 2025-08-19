<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== DEBUG LEONARDO CAMPOS FONSECA LEITE ===\n";

// Buscar Leonardo pelos dados que vemos na imagem
$leonardo = \App\Models\Person::where('name', 'LIKE', '%LEONARDO%CAMPOS%FONSECA%')
    ->orWhere('name', 'LIKE', '%LEONARDO CAMPOS FONSECA%')
    ->first();

if (!$leonardo) {
    echo "Leonardo não encontrado. Buscando por variações...\n";
    $leonardo = \App\Models\Person::where('name', 'LIKE', '%LEONARDO%')
        ->where('name', 'LIKE', '%CAMPOS%')
        ->first();
}

if ($leonardo) {
    echo "DADOS DE LEONARDO:\n";
    echo "ID: {$leonardo->id}\n";
    echo "Nome: {$leonardo->name}\n";
    echo "CPF: {$leonardo->cpf}\n";
    echo "Matrícula: {$leonardo->registration_number}\n";
    echo "Status Funcional: {$leonardo->functional_status}\n";
    echo "Tipo de Vínculo: {$leonardo->bond_type}\n";
    echo "Chefe Direto ID: {$leonardo->direct_manager_id}\n";
    
    if ($leonardo->directManager) {
        echo "Chefe Direto: {$leonardo->directManager->name} (ID: {$leonardo->directManager->id})\n";
    } else {
        echo "Chefe Direto: NENHUM\n";
    }
    
    if ($leonardo->jobFunction) {
        echo "Função: {$leonardo->jobFunction->name}\n";
        echo "É Gestor: " . ($leonardo->jobFunction->is_manager ? 'SIM' : 'NÃO') . "\n";
    } else {
        echo "Função: NENHUMA\n";
    }
    
    echo "\n=== AVALIAÇÕES DE CHEFIA ONDE LEONARDO É AVALIADOR ===\n";
    $avaliacoesChefia = \App\Models\EvaluationRequest::where('requested_person_id', $leonardo->id)
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
            
            // Verificar se Leonardo é chefe da pessoa que está avaliando
            $pessoaAvaliada = $req->evaluation->evaluatedPerson;
            if ($pessoaAvaliada->direct_manager_id == $leonardo->id) {
                echo "  -> Leonardo É CHEFE desta pessoa ✓\n";
            } else {
                echo "  -> Leonardo NÃO É CHEFE desta pessoa ✗\n";
                echo "     Chefe real da pessoa: " . ($pessoaAvaliada->directManager ? $pessoaAvaliada->directManager->name : 'NENHUM') . "\n";
            }
            echo "\n";
        }
    } else {
        echo "Leonardo não tem avaliações de chefia para fazer.\n";
    }
    
    echo "\n=== VERIFICAR SE LEONARDO DEVERIA TER CHEFE PARA AVALIAR ===\n";
    if ($leonardo->direct_manager_id) {
        $chefe = $leonardo->directManager;
        echo "Leonardo tem chefe: {$chefe->name} (ID: {$chefe->id})\n";
        
        // Verificar se existe avaliação tipo chefia para o chefe do Leonardo
        $avaliacaoDoChefe = \App\Models\Evaluation::where('evaluated_person_id', $chefe->id)
            ->where('type', 'chefia')
            ->whereHas('form', function ($q) {
                $q->where('year', now()->year);
            })
            ->first();
            
        if ($avaliacaoDoChefe) {
            echo "Existe avaliação de chefia para o chefe do Leonardo (Evaluation ID: {$avaliacaoDoChefe->id})\n";
            
            // Verificar se Leonardo tem request para avaliar o chefe
            $requestAvaliarChefe = \App\Models\EvaluationRequest::where('evaluation_id', $avaliacaoDoChefe->id)
                ->where('requested_person_id', $leonardo->id)
                ->first();
                
            if ($requestAvaliarChefe) {
                echo "Leonardo TEM request para avaliar o chefe (Request ID: {$requestAvaliarChefe->id})\n";
                echo "Status: {$requestAvaliarChefe->status}\n";
            } else {
                echo "Leonardo NÃO TEM request para avaliar o chefe\n";
            }
        } else {
            echo "NÃO existe avaliação de chefia para o chefe do Leonardo\n";
        }
    } else {
        echo "Leonardo NÃO tem chefe direto cadastrado\n";
    }
    
} else {
    echo "Leonardo não encontrado no banco de dados\n";
}

echo "\n=== VERIFICAR LÓGICA DO DASHBOARD ===\n";
// Simular a lógica do DashboardController
$currentYear = date('Y');
$bossForm = \App\Models\Form::where('year', $currentYear)->where('type', 'chefia')->where('release', true)->first();

if ($bossForm) {
    echo "Form de chefia encontrado: ID {$bossForm->id}, Year: {$bossForm->year}\n";
    echo "Prazo: {$bossForm->term_first} até {$bossForm->term_end}\n";
    
    if ($leonardo) {
        $bossEvalRequest = \App\Models\EvaluationRequest::where('requested_person_id', $leonardo->id)
            ->whereHas('evaluation', function ($q) {
                $q->where('type', 'chefia');
            })
            ->first();
            
        if ($bossEvalRequest) {
            echo "Leonardo tem EvaluationRequest para chefia: ID {$bossEvalRequest->id}\n";
            echo "Status: {$bossEvalRequest->status}\n";
            echo "Evaluation ID: {$bossEvalRequest->evaluation_id}\n";
            
            $evaluation = $bossEvalRequest->evaluation;
            echo "Pessoa sendo avaliada: {$evaluation->evaluatedPerson->name}\n";
            
            // Aqui está o problema provável
            if ($evaluation->evaluated_person_id == $leonardo->id) {
                echo "*** PROBLEMA ENCONTRADO: Leonardo está avaliando A SI MESMO como chefe! ***\n";
            }
        } else {
            echo "Leonardo NÃO tem EvaluationRequest para chefia\n";
        }
    }
} else {
    echo "Form de chefia não encontrado ou não liberado\n";
}
