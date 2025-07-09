<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\Person;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\DB;
use App\Models\Evaluation;
use App\Models\EvaluationRequest;
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
        $user = User::where('id', '=', auth()->id())->first(['id', 'name', 'cpf']);
        $cpf = '06623986618';

        $Person = Person::with('organizationalUnit.allParents')
            ->where('cpf', $user->cpf)
            ->first();

        //dd($Person);

        if (!$Person) {
            // Retorna um erro se o usuário não tiver um registro 'Person' associado
            return redirect()->route('dashboard') // ou outra rota apropriada
                ->with('error', 'Dados de servidor não encontrados para o seu usuário.');
        }

        // 2. Busca as solicitações de avaliação de chefia pendentes para este gestor
        $pendingEvaluations = EvaluationRequest::where('requested_person_id', $Person->id) // O gestor é o solicitante
            //->where('status', 'pending') // Filtra apenas pelas pendentes
            ->whereHas('evaluation', function ($query) {
                $query->where('type', 'chefia'); // Garante que a avaliação é do tipo 'chefia'
            })
            ->with([
                // Carrega os dados da pessoa a ser avaliada (o subordinado)
                'requester.organizationalUnit.allParents',
                // Carrega o formulário completo associado a esta avaliação
                'evaluation.form.groupQuestions.questions'
            ])
            ->first();


        $type = $pendingEvaluations->evaluation->type;
        $personManager = $pendingEvaluations ? $pendingEvaluations->requester : null;

        // 3. Renderiza a página, passando a lista de avaliações pendentes
        // A página Vue poderá então exibir uma lista como: "Avaliar 'Fulano'", "Avaliar 'Ciclano'"
        return Inertia::render('Evaluation/AvaliacaoPage', [ // Pode ser uma nova página ou a sua página de avaliação
            'form' => $chefiaForm,
            'person' => $personManager,
            'type' => $type,
            'evaluationRequest' => $pendingEvaluations,
        ]);

    }

    /**
     * Salva as respostas de qualquer tipo de avaliação.
     */
    public function store(Request $request, Form $form)
    {
        dd($request->all());
       
        // 1. Validação dos dados recebidos
        $validated = $request->validate([
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|integer|exists:questions,id',
            'answers.*.score' => 'required|integer|min:0|max:100',
            'evaluated_user_id' => 'required|integer|exists:users,id',
            'evaluation_request_id' => 'required|integer|exists:evaluation_requests,id'
        ]);
        dd($validated);
        DB::transaction(function () use ($validated, $form, $request) {
            // 2. Cria o registro da avaliação
            $evaluation = Evaluation::create([
                'type' => $form->type, // Pega o tipo ('chefia', 'servidor', 'autoavaliação') do formulário
                'form_id' => $form->id,
                'evaluated_user_id' => $validated['evaluated_user_id'],
                'evaluator_user_id' => auth()->id(), // Pega o ID do usuário que está logado e salvando
                'evaluation_date' => now(),
                'status' => 'completed',
            ]);

            // 3. Salva cada resposta individualmente
            foreach ($validated['answers'] as $answerData) {
                Answer::create([
                    'evaluation_id' => $evaluation->id,
                    'question_id' => $answerData['question_id'],
                    'response_content' => $answerData['score'],
                    'subject_person_id' => $validated['evaluated_user_id'],
                ]);
            }
            
            // 4. Atualiza o status da solicitação de avaliação para 'completed'
            $evaluationRequest = EvaluationRequest::find($validated['evaluation_request_id']);
            if ($evaluationRequest) {
                $evaluationRequest->status = 'completed';
                $evaluationRequest->save();
            }
        });

        // 5. Redireciona de volta com mensagem de sucesso
        return redirect()->route('evaluations')->with('success', 'Avaliação salva com sucesso!');
    }

    /**
     * Verifica a disponibilidade do formulário de autoavaliação, incluindo a regra de prazo.
     */
    public function checkAutoavaliacaoFormStatus()
    {
        $currentYear = date('Y');
        $now = now(); // Pega a data e hora atuais

        // 1. Busca o formulário do ano corrente para o tipo 'autoavaliacao'
        $autoavaliacaoForm = Form::where('type', 'servidor') // ALTERADO
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
        // 1. Obter o usuário autenticado e seu registro 'Person' correspondente.
        $user = auth()->user();
        if (!$user || !$user->cpf) {
            return redirect()->route('evaluations')->with('error', 'CPF não encontrado para o usuário autenticado.');
        }

        $user->cpf = '06623986618';

        $person = Person::where('cpf', operator: $user->cpf)->first();

        if (!$person) {
            return redirect()->route('evaluations')
                ->with('error', 'Dados de servidor não encontrados para o seu usuário.');
        }

        // 2. Buscar a SOLICITAÇÃO de autoavaliação pendente ('pending') para esta pessoa.
        $pendingRequest = EvaluationRequest::where('requested_person_id', $person->id)
            ->where('status', 'pending')
            ->whereHas('evaluation', function ($query) {
                $query->where('type', 'autoavaliação');
            })
            ->with([
                // Carrega o formulário completo, incluindo grupos e perguntas
                'evaluation.form.groupQuestions.questions'
            ])
            ->first();

        // 3. Se uma solicitação pendente for encontrada, exibe o formulário.
        if ($pendingRequest) {

            $type = $pendingRequest->evaluation->type;
            $autoavaliacaoForm = $pendingRequest->evaluation->form;

            // Carrega os relacionamentos da pessoa necessários para a view
            $person->load('organizationalUnit.allParents');

            // Renderiza a página de avaliação com o formulário e os dados da pessoa
            return Inertia::render('Evaluation/AvaliacaoPage', [
                'form' => $autoavaliacaoForm,
                'person' => $person,
                'type' => $type,
                'evaluationRequest' => $pendingRequest,
            ]);
        } else {
            // 4. Se não houver solicitação pendente, redireciona com a mensagem de que já foi preenchida.
            return redirect()->route('evaluations')
                ->with('error', 'A avaliação já foi preenchida/enviada.');
        }
    }
    /**
     * VERIFICAÇÃO: Verifica se o usuário é um gestor com avaliações pendentes da equipe.
     */
    public function checkManagerEvaluationStatus()
    {
        // 1. Pega os dados da pessoa logada
        $user = auth()->user();

        $user->cpf = '10798101610';
        $manager = Person::where('cpf', operator: $user->cpf)->first();
        
        // 2. Se não for uma pessoa ou não tiver cargo de chefia, não está disponível
        if (!$manager || is_null($manager->current_function)) {
            return response()->json(['available' => false]);
        
        }
    
        // 3. Verifica se existem solicitações PENDENTES onde este gestor é o AVALIADOR
        $hasPending = EvaluationRequest::where('requested_person_id', $manager->id)
            ->where('status', 'pending')
            ->whereHas('evaluation', function ($query) {
                // Avaliações de cima para baixo (tipos 'servidor' ou 'gestor')
                $query->whereIn('type', ['servidor', 'gestor']);
            })
            ->exists(); // 'exists()' é mais eficiente que 'count()' aqui
            
            return response()->json(['available' => $hasPending]); 
    } 

    /**
     * EXIBIÇÃO: Mostra a lista de subordinados para o gestor avaliar.
     */
    public function showSubordinatesList()
    {
        $user = auth()->user();

        $user->cpf = '10798101610';
        $manager = Person::where('cpf', operator: $user->cpf)->first();

        if (!$manager) {
        return redirect()->route('dashboard')
            ->with('error', 'Seu registro de gestor não foi encontrado.');
    }

        // Busca TODAS as solicitações (pendentes e concluídas) onde o gestor avalia a equipe
        $evaluationRequests = EvaluationRequest::where('requested_person_id', $manager->id)
            ->whereHas('evaluation', function ($query) {
                $query->whereIn('type', ['servidor', 'gestor']);
            })
            ->with([
                // Carrega os dados da pessoa AVALIADA (o subordinado)
                'evaluation.evaluated:id,name,current_position',
            ])
            ->get();

              
        // Renderiza uma NOVA PÁGINA VUE, passando a lista de solicitações
        return Inertia::render('Evaluation/SubordinatesList', [
            'requests' => $evaluationRequests,
        ]);
    }

    /**
     * EXIBIÇÃO: Mostra o formulário para avaliar um subordinado específico.
     * @param EvaluationRequest $evaluationRequest O ID da solicitação vindo da URL
     */
    public function showSubordinateEvaluationForm(EvaluationRequest $evaluationRequest)
    {
    

        // Carrega todos os dados necessários
        $evaluationRequest->load([
            'evaluation.form.groupQuestions.questions',
            'evaluation.evaluated.organizationalUnit.allParents'
        ]);

        return Inertia::render('Evaluation/AvaliacaoPage', [
            'form' => $evaluationRequest->evaluation->form,
            // Os dados da pessoa na tela são do SUBORDINADO
            'person' => $evaluationRequest->evaluation->evaluated,
            'evaluationRequest' => $evaluationRequest,
            // Passa o tipo da avaliação para o título dinâmico na AvaliacaoPage
            'type' => $evaluationRequest->evaluation->type,
        ]);
    }
}