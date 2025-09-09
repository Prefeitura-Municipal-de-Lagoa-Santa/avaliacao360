<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Person;
use App\Models\EvaluationRequest;

echo "=== DEBUG SCORE 60 - MÉTODO myEvaluationsHistory ===\n";

$person = Person::where('cpf', '15570391606')->first();

if (!$person) {
    echo "Pessoa não encontrada!\n";
    exit;
}

echo "Pessoa: {$person->name}\n";
echo "CPF: {$person->cpf}\n";
echo "Matrícula: {$person->registration_number}\n";
echo "É gestor: " . ($person->jobFunction && $person->jobFunction->is_manager ? 'SIM' : 'NÃO') . "\n";
echo "Função: " . ($person->jobFunction ? $person->jobFunction->name : 'N/A') . "\n\n";

// ESTA É A QUERY CORRETA DO MÉTODO myEvaluationsHistory
$requests = EvaluationRequest::with([
    'evaluation.form.groupQuestions.questions',
    'evaluation.form',
    'requestedPerson',
])
->where('requester_person_id', $person->id)  // REQUISIÇÕES FEITAS PELA PESSOA
->get();

echo "Total de requisições encontradas: " . $requests->count() . "\n\n";

$anos = $requests->map(function ($req) {
    $form = $req->evaluation?->form;
    return $form?->year ?? ($form?->period ? \Carbon\Carbon::parse($form->period)->format('Y') : null);
})
->filter()
->unique()
->sortDesc()
->values();

echo "Anos encontrados: " . $anos->implode(', ') . "\n\n";

foreach ($anos as $ano) {
    echo "=== PROCESSANDO ANO $ano ===\n";
    
    $requestsAno = $requests->filter(function ($req) use ($ano) {
        $form = $req->evaluation?->form;
        $year = $form?->year ?? ($form?->period ? \Carbon\Carbon::parse($form->period)->format('Y') : null);
        return $year == $ano;
    });
    
    echo "Requisições para o ano $ano: " . $requestsAno->count() . "\n";
    
    foreach ($requestsAno as $req) {
        echo "  - Tipo: " . ($req->evaluation->type ?? 'N/A') . "\n";
        echo "    Pessoa avaliada: " . ($req->requestedPerson->name ?? 'N/A') . "\n";
        echo "    Status: {$req->status}\n";
    }
    
    $autoTypes = ['autoavaliaçãogestor', 'autoavaliaçãocomissionado', 'autoavaliação'];
    $chefiaTypes = ['servidor', 'gestor', 'comissionado'];
    
    $auto = $requestsAno->first(fn($r) => $r->requested_person_id == $person->id && in_array(strtolower($r->evaluation->type ?? ''), $autoTypes));
    $chefia = $requestsAno->first(fn($r) => in_array(strtolower($r->evaluation->type ?? ''), $chefiaTypes) && $r->requested_person_id == $person->direct_manager_id);
    
    $equipes = $requestsAno->filter(
        fn($r) =>
        str_contains(strtolower($r->evaluation->type ?? ''), 'equipe') ||
        (strtolower($r->evaluation->type ?? '') === 'chefia' && $r->requested_person_id !== $person->direct_manager_id)
    );
    
    echo "\nTipos de avaliação encontrados:\n";
    echo "Auto: " . ($auto ? "SIM (tipo: {$auto->evaluation->type})" : "NÃO") . "\n";
    echo "Chefia: " . ($chefia ? "SIM (tipo: {$chefia->evaluation->type})" : "NÃO") . "\n";
    echo "Equipe: " . ($equipes->count() > 0 ? "SIM ({$equipes->count()} avaliações)" : "NÃO") . "\n";
    
    // Simular função de cálculo
    $getNotaPonderada = function ($request) {
        if (!$request || $request->status !== 'completed') return null;
        
        $form = $request->evaluation?->form;
        $groups = $form?->groupQuestions ?? [];
        $answers = \App\Models\Answer::where('evaluation_id', $request->evaluation_id)->get();
        
        $somaNotas = 0;
        $somaPesos = 0;
        foreach ($groups as $group) {
            foreach ($group->questions as $question) {
                $answer = $answers->firstWhere('question_id', $question->id);
                if ($answer && $answer->score !== null) {
                    $somaNotas += intval($answer->score) * $question->weight;
                    $somaPesos += $question->weight;
                }
            }
        }
        return $somaPesos > 0 ? round($somaNotas / $somaPesos) : 0;
    };
    
    $notaAuto = $auto ? $getNotaPonderada($auto) : null;
    $notaChefia = $chefia ? $getNotaPonderada($chefia) : null;
    $notaEquipe = $equipes->count() > 0 ? round($equipes->avg(fn($r) => $getNotaPonderada($r)), 2) : null;
    
    echo "\nNotas calculadas:\n";
    echo "Nota Auto: " . ($notaAuto ?? 'NULL') . "\n";
    echo "Nota Chefia: " . ($notaChefia ?? 'NULL') . "\n";
    echo "Nota Equipe: " . ($notaEquipe ?? 'NULL') . "\n";
    
    $isGestor = $person->jobFunction && $person->jobFunction->is_manager;
    
    echo "\n=== CÁLCULO DA NOTA FINAL ===\n";
    echo "É gestor: " . ($isGestor ? 'SIM' : 'NÃO') . "\n";
    
    if ($isGestor) {
        if ($notaAuto === null || $notaChefia === null || $notaEquipe === null) {
            $notaFinal = 0;
            echo "RESULTADO: Nota zerada por ausência de avaliação obrigatória\n";
            if ($notaEquipe === null) {
                echo "MOTIVO: Ausência de avaliação de equipe (obrigatória para gestores)\n";
            } else {
                echo "MOTIVO: Ausência de autoavaliação ou avaliação de chefia\n";
            }
        } else {
            $notaFinal = round(($notaAuto * 0.25) + ($notaChefia * 0.5) + ($notaEquipe * 0.25), 2);
            echo "RESULTADO: ({$notaAuto} x 25%) + ({$notaChefia} x 50%) + ({$notaEquipe} x 25%) = {$notaFinal}\n";
        }
    } else {
        if ($notaAuto === null || $notaChefia === null) {
            $notaFinal = 0;
            echo "RESULTADO: Nota zerada por ausência de avaliação obrigatória\n";
        } else {
            $notaFinal = round(($notaAuto * 0.3) + ($notaChefia * 0.7), 2);
            echo "RESULTADO: ({$notaAuto} x 30%) + ({$notaChefia} x 70%) = {$notaFinal}\n";
        }
    }
    
    echo "\nNOTA FINAL CALCULADA PARA O ANO $ano: {$notaFinal}\n";
    echo "=====================================\n\n";
}
