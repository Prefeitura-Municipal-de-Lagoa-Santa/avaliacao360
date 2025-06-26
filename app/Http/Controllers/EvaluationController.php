<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\Person;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\DB;
use App\Models\Evaluation;
use App\Models\Answer;
use App\Models\User;
use App\Models\OrganizationalUnit;


class EvaluationController extends Controller
{

    /**
     * Verifica a disponibilidade do formulário de chefia, incluindo a regra de prazo.
     */
    public function checkChefiaFormStatus()
    {
        $currentYear = date('Y');
        $now = now(); // Pega a data e hora atuais

        // 1. Busca o formulário do ano corrente
        $chefiaForm = Form::where('type', 'chefia')
                            ->where('year', $currentYear)
                            ->first();

        // 2. Verifica as condições de disponibilidade
        if (!$chefiaForm) {
            return response()->json([
                'available' => false,
                'message' => 'Não há formulário de avaliação da chefia configurado para este ano.'
            ]);
        }

        if (!$chefiaForm->release) {
            return response()->json([
                'available' => false,
                'message' => 'O formulário de avaliação da chefia ainda não foi liberado pela administração.'
            ]);
        }

        if (!$chefiaForm->term_first || !$chefiaForm->term_end) {
            return response()->json([
                'available' => false,
                'message' => 'O período para preenchimento da avaliação ainda não foi definido.'
            ]);
        }

        // 3. Verifica se a data atual está dentro do prazo
        if (!$now->between($chefiaForm->term_first, $chefiaForm->term_end)) {
            $startDate = $chefiaForm->term_first->format('d/m/Y');
            $endDate = $chefiaForm->term_end->format('d/m/Y');
            return response()->json([
                'available' => false,
                'message' => "Fora do prazo. O formulário está disponível para preenchimento apenas entre {$startDate} e {$endDate}."
            ]);
        }

        // 4. Se todas as verificações passarem, o formulário está disponível
        return response()->json(['available' => true]);
    }


    /**
     * Exibe o formulário de avaliação da chefia.
     */
    public function showChefiaForm()
    {
        $currentYear = date('Y');

        // Altera de firstOrFail() para first() para não gerar erro 404
        $chefiaForm = Form::where('type', 'chefia')
                            ->where('year', $currentYear)
                            ->where('release', true)
                            ->with('groupQuestions.questions')
                            ->first();

                         
        // Se o formulário não for encontrado, redireciona de volta com uma mensagem de erro
        if (!$chefiaForm) {
            return redirect()->route('evaluations')
                             ->with('error', 'A avaliação da chefia para este período ainda não foi liberada.');
        }
      
        // Se o formulário existir, continua normalmente
        $users = User::where('id', '=', auth()->id())->get(['id', 'name', 'cpf']);
        //$person = Person::where('cpf', $users['cpf']);
        //dd($users);
        return Inertia::render('Evaluation/ChefiaEvaluationPage', [
            'form' => $chefiaForm,
            'users' => $users,
            
            
        ]);

    }

    /**
     * Salva as respostas da avaliação da chefia.
     */
    public function storeChefiaEvaluation(Request $request, Form $form)
    {
        // Valida os dados recebidos do formulário Vue.js
        $validated = $request->validate([
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|integer|exists:questions,id',
            'answers.*.score' => 'required|integer|min:0|max:100',
            'evaluated_user_id' => 'required|integer|exists:users,id'
        ]);
        
        // Utiliza uma transação para garantir que todas as operações no banco de dados sejam bem-sucedidas.
        DB::transaction(function () use ($validated, $form) {
            // 1. Cria um novo registo de avaliação
            //    A chamada foi corrigida para corresponder às colunas da sua tabela 'evaluations'
            $evaluation = Evaluation::create([
                'type' => 'chefia', // Adicionada a coluna 'type', que estava em falta.
                'form_id' => $form->id,
                'evaluated_user_id' => $validated['evaluated_user_id'],
                // As colunas 'evaluator_user_id', 'status' e 'evaluation_date' foram removidas
                // porque não existem na sua migração atual.
            ]);

            // 2. Salva cada resposta na tabela 'answers'
            foreach ($validated['answers'] as $answerData) {
                Answer::create([
                    'evaluation_id' => $evaluation->id,
                    'question_id' => $answerData['question_id'],
                    'response_content' => $answerData['score'], // Guarda a pontuação
                    'subject_user_id' => $validated['evaluated_user_id'],
                ]);
            }
        });

        // Redireciona para a página de avaliações com uma mensagem de sucesso.
        // O nome da rota foi corrigido de 'evaluations.index' para 'evaluations'.
        return redirect()->route('evaluations')->with('success', 'Avaliação salva com sucesso!');
    }

    // Adicione este método ao seu EvaluationController.php

/**
 * Verifica a disponibilidade do formulário de autoavaliação, incluindo a regra de prazo.
 */
public function checkAutoavaliacaoFormStatus()
{
    $currentYear = date('Y');
    $now = now(); // Pega a data e hora atuais

    // 1. Busca o formulário do ano corrente para o tipo 'autoavaliacao'
    $autoavaliacaoForm = Form::where('type', 'autoavaliacao') // ALTERADO
                            ->where('year', $currentYear)
                            ->first();

    // 2. Verifica as condições de disponibilidade
    if (!$autoavaliacaoForm) {
        return response()->json([
            'available' => false,
            'message' => 'Não há formulário de autoavaliação configurado para este ano.'
        ]);
    }

    if (!$autoavaliacaoForm->release) {
        return response()->json([
            'available' => false,
            'message' => 'O formulário de autoavaliação ainda não foi liberado pela administração.'
        ]);
    }

    if (!$autoavaliacaoForm->term_first || !$autoavaliacaoForm->term_end) {
        return response()->json([
            'available' => false,
            'message' => 'O período para preenchimento da autoavaliação ainda não foi definido.'
        ]);
    }

    // 3. Verifica se a data atual está dentro do prazo
    if (!$now->between($autoavaliacaoForm->term_first, $autoavaliacaoForm->term_end)) {
        $startDate = $autoavaliacaoForm->term_first->format('d/m/Y');
        $endDate = $autoavaliacaoForm->term_end->format('d/m/Y');
        return response()->json([
            'available' => false,
            'message' => "Fora do prazo. O formulário está disponível para preenchimento apenas entre {$startDate} e {$endDate}."
        ]);
    }

    // 4. Se todas as verificações passarem, o formulário está disponível
    return response()->json(['available' => true]);
}
// Adicione este método ao seu EvaluationController.php

/**
 * Exibe o formulário de autoavaliação.
 */
public function showAutoavaliacaoForm()
{
    $currentYear = date('Y');

    $autoavaliacaoForm = Form::where('type', 'autoavaliacao') // ALTERADO
                        ->where('year', $currentYear)
                        ->where('release', true)
                        ->with('groupQuestions.questions')
                        ->first();

    if (!$autoavaliacaoForm) {
        return redirect()->route('evaluations')
                         ->with('error', 'A autoavaliação para este período ainda não foi liberada.');
    }
  
    // Esta lógica já busca o usuário autenticado, o que é perfeito para a autoavaliação.
    $user = User::where('id', '=', auth()->id())->first(['cpf']);
    $person = Person::with('organizationalUnit.allParents')
                  ->where('cpf', $user->cpf)
                  ->first();
    if(!$person){
        return redirect()->route('evaluations')
                         ->with('error', 'A autoavaliação para este período ainda não foi liberada.');
    }
    // Vamos renderizar uma nova página Vue para a autoavaliação.
    return Inertia::render('Evaluation/AutoavaliacaoPage', [ // ALTERADO
        'form' => $autoavaliacaoForm,
        'person' => $person,
    ]);
}
}