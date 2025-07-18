<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\Person;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Evaluation;
use App\Models\EvaluationRequest;
use App\Models\User;


class EvaluationController extends Controller
{
    /**
     * Salva as respostas de qualquer tipo de avaliação.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|integer|exists:questions,id',
            'answers.*.score' => 'required|integer|min:0|max:100',
            'evaluation_request_id' => 'required|exists:evaluation_requests,id',
            'evidencias' => 'nullable|string',
            'assinatura_base64' => 'required|string',
        ]);

        DB::beginTransaction();
        try {
            $evaluationRequest = EvaluationRequest::findOrFail($data['evaluation_request_id']);
            $evaluation = $evaluationRequest->evaluation;

            if (!$evaluation) {
                throw new \Exception('Avaliação não encontrada para esta solicitação.');
            }

            // Opcional: Deletar respostas antigas para sobrescrever (ou atualize, se preferir)
            $evaluation->answers()->delete();

            // Salva as novas respostas
            foreach ($data['answers'] as $answer) {
                $evaluation->answers()->create([
                    'question_id' => $answer['question_id'],
                    'score' => $answer['score'],
                    'subject_person_id' => $evaluationRequest->requested_person_id,
                ]);
            }

            // Atualiza evidências, assinatura e status na EvaluationRequest
            $evaluationRequest->update([
                'evidencias' => $data['evidencias'],
                'assinatura_base64' => $data['assinatura_base64'],
                'status' => 'completed',
            ]);

            DB::commit();

            return redirect()->route('evaluations')->with('success', 'Avaliação salva com sucesso!');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Erro ao salvar avaliação', [
                'user_id' => auth()->id(),
                'request_data' => $request->all(),
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->withErrors(['error' => 'Erro ao salvar avaliação. O erro foi registrado para análise.']);
        }
    }


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

        $chefiaForm = Form::where('type', 'chefia')
            ->where('year', $currentYear)
            ->where('release', true)
            ->with('groupQuestions.questions')
            ->first();

        if (!$chefiaForm) {
            return redirect()->route('evaluations')
                ->with('error', 'A avaliação da chefia para este período ainda não foi liberada.');
        }

        $user = User::where('id', '=', auth()->id())->first(['id', 'name', 'cpf']);
        $Person = Person::with('organizationalUnit.allParents', 'jobFunction')
            ->where('cpf', $user->cpf)
            ->first();

        if (!$Person) {
            return redirect()->route('dashboard')
                ->with('error', 'Dados de servidor não encontrados para o seu usuário.');
        }

        $pendingEvaluations = EvaluationRequest::where('requested_person_id', $Person->id)
            ->whereHas('evaluation', function ($query) {
                $query->where('type', 'chefia');
            })
            ->with([
                'requester.organizationalUnit.allParents',
                'requester.jobFunction', // Carrega jobFunction do avaliado
                'evaluation.form.groupQuestions.questions'
            ])
            ->first();

        $type = $pendingEvaluations->evaluation->type ?? null;
        $personManager = $pendingEvaluations ? $pendingEvaluations->requester : null;

        return Inertia::render('Evaluation/AvaliacaoPage', [
            'form' => $chefiaForm,
            'person' => $personManager,
            'type' => $type,
            'evaluationRequest' => $pendingEvaluations,
        ]);
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
        $user = auth()->user();
        if (!$user || !$user->cpf) {
            return redirect()->route('evaluations')->with('error', 'CPF não encontrado para o usuário autenticado.');
        }

        // Carregando o relacionamento jobFunction e organizationalUnit.allParents
        $person = Person::with('jobFunction', 'organizationalUnit.allParents')
            ->where('cpf', $user->cpf)
            ->first();

        if (!$person) {
            return redirect()->route('evaluations')
                ->with('error', 'Dados de servidor não encontrados para o seu usuário.');
        }

        $evaluationRequest = EvaluationRequest::where('requested_person_id', $person->id)
            ->whereIn('status', ['pending', 'completed'])
            ->whereHas('evaluation', function ($query) {
                $query->whereIn('type', [
                    'autoavaliação',
                    'autoavaliaçãoGestor',
                    'autoavaliaçãoComissionado',
                ]);
            })
            ->with([
                'evaluation.answers',
                'evaluation.form.groupQuestions.questions',
                'evaluation.evaluated.jobFunction', // importante caso precise do avaliado (normalmente igual ao $person)
            ])
            ->latest()
            ->first();

        if ($evaluationRequest) {
            $type = $evaluationRequest->evaluation->type;
            $autoavaliacaoForm = $evaluationRequest->evaluation->form;

            // já está carregado com jobFunction
            // $person->load('organizationalUnit.allParents');

            return Inertia::render('Evaluation/AvaliacaoPage', [
                'form' => $autoavaliacaoForm,
                'person' => $person,
                'type' => $type,
                'evaluationRequest' => $evaluationRequest,
                'answers' => $evaluationRequest->evaluation->answers ?? [],
                'evidencias' => $evaluationRequest->evidencias ?? '',
                'assinatura_base64' => $evaluationRequest->assinatura_base64 ?? '',
                'status' => $evaluationRequest->status,
            ]);
        } else {
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
        $manager = Person::where('cpf', Auth::user()->cpf)->first();

        if (!$manager) {
            return redirect()->route('dashboard')
                ->with('error', 'Seu registro de gestor não foi encontrado.');
        }

        // Busca TODAS as solicitações (pendentes e concluídas) onde o gestor avalia a equipe
        $evaluationRequests = EvaluationRequest::where('requested_person_id', $manager->id)
            ->whereHas('evaluation', function ($query) use ($manager) {
                $query->whereIn('type', ['servidor', 'gestor', 'comissionado'])
                    // Aqui exclui o próprio gestor da lista de avaliados
                    ->where('evaluated_person_id', '!=', $manager->id);
            })
            ->with([
                'evaluation.evaluated:id,name,current_position,job_function_id',
                'evaluation.evaluated.jobFunction:id,name',
            ])
            ->get();

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
        $evaluationRequest->load([
            'evaluation.form.groupQuestions.questions',
            'evaluation.evaluated.organizationalUnit.allParents',
            'evaluation.evaluated.jobFunction', // Carrega função/cargo do subordinado
        ]);

        return Inertia::render('Evaluation/AvaliacaoPage', [
            'form' => $evaluationRequest->evaluation->form,
            'person' => $evaluationRequest->evaluation->evaluated,
            'evaluationRequest' => $evaluationRequest,
            'type' => $evaluationRequest->evaluation->type,
        ]);
    }


    public function pending(Request $request)
    {
        $search = $request->input('search');

        $query = EvaluationRequest::with([
            'evaluation.form',
            'evaluation.evaluatedPerson',
            'requestedPerson'
        ])->where('status', 'pending');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('evaluation.evaluatedPerson', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                })
                    ->orWhereHas('requestedPerson', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $pendingRequests = $query
            ->orderByDesc('created_at')
            ->paginate(20)
            ->through(function ($request) {
                return [
                    'id' => $request->id,
                    'type' => $request->evaluation->type ?? '-',
                    'form_name' => $request->evaluation->form->name ?? '-',
                    'avaliado' => $request->evaluation->evaluatedPerson->name ?? '-',
                    'avaliador' => $request->requestedPerson->name ?? '-',
                    'created_at' => $request->created_at ? $request->created_at->format('d/m/Y H:i') : '',
                ];
            })
            ->withQueryString();

        return inertia('Evaluations/Pending', [
            'pendingRequests' => $pendingRequests,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function showEvaluationResult(EvaluationRequest $evaluationRequest)
    {
        // Carrega todos os relacionamentos necessários para a tela de resultado
        $evaluationRequest->load([
            'evaluation.answers',
            'evaluation.form.groupQuestions.questions',
            'evaluation.evaluated.jobFunction', // função/cargo do avaliado
            'evaluation.evaluated.organizationalUnit.allParents'
        ]);

        // Pode ser necessário ajustar para pegar campos default, caso estejam nulos.
        $evaluated = $evaluationRequest->evaluation->evaluated;

        return Inertia::render('Evaluation/AvaliacaoResultadoPage', [
            'form' => $evaluationRequest->evaluation->form,
            'person' => $evaluated,
            'type' => $evaluationRequest->evaluation->type,
            'evaluation' => [
                'answers' => $evaluationRequest->evaluation->answers,
                'evidencias' => $evaluationRequest->evidencias,
                'assinatura_base64' => $evaluationRequest->assinatura_base64,
                'updated_at' => $evaluationRequest->updated_at,
            ],
        ]);
    }

}