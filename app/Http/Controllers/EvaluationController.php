<?php

namespace App\Http\Controllers;

use App\Models\Acknowledgment;
use App\Models\Answer;
use App\Models\Form;
use App\Models\Person;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\EvaluationRequest;
use App\Models\User;
use App\Models\configs as Config; // Importar o model de configurações

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
        if (
            !$now->between(
                Carbon::parse($chefiaForm->term_first),
                Carbon::parse($chefiaForm->term_end)->endOfDay()
            )
        ) {

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
        $cpf= '10798101610';
        $Person = Person::with('organizationalUnit.allParents', 'jobFunction')
            ->where('cpf', $cpf)
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
        if (
            !$now->between(
                Carbon::parse($autoavaliacaoForm->term_first),
                Carbon::parse($autoavaliacaoForm->term_end)->endOfDay()
            )
        ) {

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

    public function completed(Request $request)
    {
        $search = $request->input('search');

        $query = EvaluationRequest::with([
            'evaluation.form',
            'evaluation.evaluatedPerson',
            'requestedPerson'
        ])->where('status', 'completed');

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

        $completedRequests = $query
        ->orderByDesc('created_at')
        ->paginate(20)
        ->through(function ($request) {
            $form = $request->evaluation->form;
            $canDelete = false;
            
            if (auth()->user() && auth()->user()->roles (['Admin', 'RH']) && $form) {

                $now = now();
             
    if ($form->term_first && $form->term_end && $now->between($form->term_first, $form->term_end->endOfDay())) {
                    $canDelete = true;
                }
            }
           
            return [
                'id' => $request->id,
                'type' => $request->evaluation->type ?? '-',
                'form_name' => $request->evaluation->form->name ?? '-',
                'avaliado' => $request->evaluation->evaluatedPerson->name ?? '-',
                'avaliador' => $request->requestedPerson->name ?? '-',
                'created_at' => $request->created_at?->format('d/m/Y H:i') ?? '',
                'can_delete' => $canDelete,
            ];
        })
        
        ->withQueryString();
         
    return inertia('Evaluations/Completed', [
        'completedRequests' => $completedRequests,
        'filters' => [
            'search' => $search,
        ],
    ]);
}

    public function deleteCompleted($id)
{
    $evaluationRequest = EvaluationRequest::findOrFail($id);

    if (!auth()->user()->roles(['Admin', 'RH'])) {
        abort(403, 'Sem permissão');
    }

    // Verifique se está dentro do período
    $form = $evaluationRequest->evaluation->form;
    $now = now();
    if ($now->lt($form->term_first) || $now->gt($form->term_end)) {
        return back()->withErrors(['error' => 'Fora do período de avaliação']);
    }

    DB::beginTransaction();
    try {
        // Registra quem fez a ação
        $evaluationRequest->deleted_by = auth()->id();
        $evaluationRequest->deleted_at = now();
        
        // Volta para status pending
        $evaluationRequest->status = 'pending';
        $evaluationRequest->save();

        // Deleta as respostas associadas
        Answer::where('evaluation_id', $evaluationRequest->evaluation_id)->delete();

        // Remove evidências e assinatura
        $evaluationRequest->update([
            'evidencias' => null,
            'assinatura_base64' => null
        ]);

        DB::commit();
        return back()->with('success', 'Avaliação retornada para pendente com sucesso!');
    } catch (\Exception $e) {
        DB::rollBack();
        \Log::error('Erro ao reverter avaliação:', [
            'evaluation_id' => $id,
            'user_id' => auth()->id(),
            'error' => $e->getMessage()
        ]);
        return back()->withErrors(['error' => 'Erro ao reverter avaliação.']);
    }
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

    public function myEvaluationsHistory()
    {
        $user = Auth::user();
        $person = Person::where('cpf', $user->cpf)->first();

        if (!$person) {
            return inertia('Dashboard/MyEvaluations', [
                'evaluations' => [],
                'acknowledgments' => [],
            ]);
        }

        $requests = EvaluationRequest::with([
            'evaluation.form.groupQuestions.questions',
            'evaluation.form',
            'requestedPerson',
        ])
            ->where('requester_person_id', $person->id)
            ->get();

        $anos = $requests->map(function ($req) {
            $form = $req->evaluation?->form;
            return $form?->year ?? ($form?->period ? \Carbon\Carbon::parse($form->period)->format('Y') : null);
        })
            ->filter()
            ->unique()
            ->sortDesc()
            ->values();

        $acknowledgments = Acknowledgment::where('person_id', $person->id)
            ->get(['year', 'signature_base64', 'created_at', 'signed_at'])
            ->map(fn($ack) => [
                'year' => $ack->year,
                'signature_base64' => $ack->signature_base64,
                'signed_at' => \Carbon\Carbon::parse($ack->signed_at ?? $ack->created_at)->format('Y-m-d'),
            ])
            ->toArray();

        // Requisições de recursos já existentes
        $existingRecourses = \App\Models\EvaluationRecourse::where('person_id', $person->id)
            ->with('evaluation')
            ->get()
            ->keyBy('evaluation_id');

        $evaluations = [];

        foreach ($anos as $ano) {
            $requestsAno = $requests->filter(function ($req) use ($ano) {
                $form = $req->evaluation?->form;
                $year = $form?->year ?? ($form?->period ? \Carbon\Carbon::parse($form->period)->format('Y') : null);
                return $year == $ano;
            });

            $formGroups = $requestsAno->first()?->evaluation?->form?->groupQuestions ?? [];

            $autoTypes = ['autoavaliaçãogestor', 'autoavaliaçãocomissionado', 'autoavaliação'];
            $chefiaTypes = ['servidor', 'gestor', 'comissionado'];

            $getNotaPonderada = function ($request) {
                if (!$request)
                    return 0;
                $form = $request->evaluation?->form;
                $groups = $form?->groupQuestions ?? [];
                $answers = Answer::where('evaluation_id', $request->evaluation_id)->get();

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

            $auto = $requestsAno->first(fn($r) => $r->requested_person_id == $person->id && in_array(strtolower($r->evaluation->type ?? ''), $autoTypes));
            $chefia = $requestsAno->first(fn($r) => in_array(strtolower($r->evaluation->type ?? ''), $chefiaTypes) && $r->requested_person_id == $person->direct_manager_id);
            
            // Melhor detecção de avaliações de equipe - incluir 'chefia' que podem ser avaliações de equipe
            $equipes = $requestsAno->filter(fn($r) => 
                str_contains(strtolower($r->evaluation->type ?? ''), 'equipe') ||
                (strtolower($r->evaluation->type ?? '') === 'chefia' && $r->requested_person_id !== $person->direct_manager_id)
            );

            $notaAuto = $getNotaPonderada($auto);
            $notaChefia = $getNotaPonderada($chefia);
            $notaEquipe = $equipes->count() > 0 ? round($equipes->avg(fn($r) => $getNotaPonderada($r)), 2) : null;

            $calcAuto = $auto ? "Autoavaliação: $notaAuto" : '';
            $calcChefia = $chefia ? "Chefia: $notaChefia" : '';
            $calcEquipe = $equipes->count() ? "Equipe (média de {$equipes->count()} avaliações): $notaEquipe" : '';

            // Determinar se é gestor baseado na existência de avaliações de equipe
            $isGestor = $notaEquipe !== null && $equipes->count() > 0;
            
            // Lógica original da nota
            if (
                ($isGestor && ($notaAuto === 0 || $notaChefia === 0 || $notaEquipe === null)) ||
                (!$isGestor && ($notaAuto === 0 || $notaChefia === 0))
            ) {
                $notaFinal = 0;
                $calcFinal = 'Nota zerada por ausência de preenchimento de uma ou mais partes.';
            } else {
                $notaFinal = $isGestor
                    ? round(($notaAuto * 0.25) + ($notaChefia * 0.5) + ($notaEquipe * 0.25), 2)
                    : round(($notaAuto * 0.3) + ($notaChefia * 0.7), 2);

                $calcFinal = $isGestor
                    ? "($notaAuto x 25%) + ($notaChefia x 50%) + ($notaEquipe x 25%) = $notaFinal"
                    : "($notaAuto x 30%) + ($notaChefia x 70%) = $notaFinal";
            }

            $id = $auto?->id ?? $chefia?->id ?? $equipes->first()?->id;

            $configAno = Config::where('year', $ano)->first();
            $isInAwarePeriod = false;

            if ($configAno && $configAno->gradesPeriod) {
                $startDate = \Carbon\Carbon::parse($configAno->gradesPeriod)->startOfDay();
                $hoje = \Carbon\Carbon::now()->startOfDay();
                $isInAwarePeriod = $hoje->greaterThanOrEqualTo($startDate);
            }

            // Período de recurso
            $signedAt = null;
            $ack = collect($acknowledgments)->firstWhere('year', $ano);
            if ($ack && isset($ack['signed_at'])) {
                $signedAt = \Carbon\Carbon::parse($ack['signed_at']);
            }

            $recourseDays = $configAno?->recoursePeriod ?? 15;
            $isInRecoursePeriod = false;

            if ($signedAt) {
                $endRecourseDate = $signedAt->copy()->addDays($recourseDays)->endOfDay();
                $today = \Carbon\Carbon::now();
                $isInRecoursePeriod = $today->between($signedAt, $endRecourseDate);
            }

            $recourse = $existingRecourses->get($id);
            
            // Verificar se o recurso foi DEFERIDO e calcular nova nota
            $finalScoreAfterRecourse = null;
            $calcFinalAfterRecourse = null;
            $isRecourseApproved = false;
            
            if ($recourse && $recourse->status === 'respondido') {
                $isRecourseApproved = true;
                
                // Calcular nova nota SEM a nota do chefe (deferido)
                if ($isGestor && $notaEquipe !== null && $equipes->count() > 0) {
                    // Com equipe: 75% auto + 25% equipe
                    $finalScoreAfterRecourse = round(($notaAuto * 0.75) + ($notaEquipe * 0.25), 2);
                    $calcFinalAfterRecourse = "Recurso DEFERIDO (com equipe): ($notaAuto x 75%) + ($notaEquipe x 25%) = $finalScoreAfterRecourse";
                } else if ($notaAuto > 0) {
                    // Sem equipe: 100% auto
                    $finalScoreAfterRecourse = $notaAuto;
                    $calcFinalAfterRecourse = "Recurso DEFERIDO (sem equipe): ($notaAuto x 100%) = $finalScoreAfterRecourse";
                } else {
                    // Fallback se não há autoavaliação
                    $finalScoreAfterRecourse = 0;
                    $calcFinalAfterRecourse = "Recurso DEFERIDO: Sem dados de autoavaliação disponíveis";
                }
            }

            $evaluations[] = [
                'year' => $ano,
                'user' => $person->name,
                'final_score' => $notaFinal,
                'calc_final' => $calcFinal,
                'calc_auto' => $calcAuto,
                'calc_chefia' => $calcChefia,
                'calc_equipe' => $calcEquipe,
                'id' => $id,
                'is_in_aware_period' => $isInAwarePeriod,
                'is_in_recourse_period' => $isInRecoursePeriod,
                'has_recourse' => $recourse !== null,
                'recourse_id' => $recourse?->id,
                'recourse_status' => $recourse?->status,
                'is_recourse_approved' => $isRecourseApproved,
                'final_score_after_recourse' => $finalScoreAfterRecourse,
                'calc_final_after_recourse' => $calcFinalAfterRecourse,
            ];
        }

        return inertia('Dashboard/MyEvaluations', [
            'evaluations' => $evaluations,
            'acknowledgments' => $acknowledgments,
        ]);
    }

    public function showEvaluationDetail($id)
    {
        $user = Auth::user();
       
        $person = Person::where('cpf', $user->cpf)->first();

        $evaluationRequest = EvaluationRequest::with('evaluation.form')->findOrFail($id);

        // ✅ Se não for comissão, só pode ver se foi o avaliador
        if (!user_can('recourse')) {
            if (!$person || $evaluationRequest->requester_person_id !== $person->id) {
                abort(403, 'Você não pode acessar esta avaliação.');
            }
        }

        $evaluation = $evaluationRequest->evaluation;
        if (!$evaluation || !$evaluation->form) {
            abort(404, 'Formulário de avaliação não encontrado.');
        }

        $form = $evaluation->form;
        $year = $form->year ?? ($form->period ? \Carbon\Carbon::parse($form->period)->format('Y') : null);

        // === VERIFICA SE ESTÁ LIBERADO PARA VER DETALHES (exceto para comissão) ===
        $configAno = Config::where('year', $year)->first();
        $isLiberado = true;
        if (!user_can('recourse')) {
            if ($configAno && $configAno->gradesPeriod) {
                $startDate = \Carbon\Carbon::parse($configAno->gradesPeriod)->startOfDay();
                $hoje = \Carbon\Carbon::now()->startOfDay();
                $isLiberado = $hoje->greaterThanOrEqualTo($startDate);
            }

            if (!$isLiberado) {
                return redirect()->route('evaluations')->with('error', 'Nota final ainda não está liberada para visualização.');
            }
        }

        // Filtra todas as requisições no mesmo ano feitas pelo avaliador
        $requestsAno = EvaluationRequest::with([
            'evaluation.form.groupQuestions.questions',
            'requested',
            'requester',
        ])
            ->where('evaluation_id', $evaluation->id)
            ->get();

        $formGroups = $form->groupQuestions ?? [];

        $blocos = [];
        $equipes = [];

        foreach ($requestsAno as $r) {
            $type = strtolower($r->evaluation->type ?? '');
            if (str_contains($type, 'equipe')) {
                $equipes[] = $r;
                continue;
            }

            $answers = Answer::where('evaluation_id', $r->evaluation_id)->get();
            $byQuestion = [];
            $somaNotas = 0;
            $somaPesos = 0;

            foreach ($formGroups as $group) {
                foreach ($group->questions as $question) {
                    $answer = $answers->firstWhere('question_id', $question->id);
                    $score = $answer ? intval($answer->score) : null;
                    $byQuestion[] = [
                        'question' => $question->text_content,
                        'score' => $score,
                        'weight' => $question->weight,
                    ];
                    if ($score !== null) {
                        $somaNotas += $score * $question->weight;
                        $somaPesos += $question->weight;
                    }
                }
            }

            $nota = $somaPesos > 0 ? round($somaNotas / $somaPesos, 2) : null;

            $blocos[] = [
                'tipo' => $r->evaluation->type ?? '-',
                'nota' => $nota,
                'answers' => $byQuestion,
                'evidencias' => $r->evidencias ?? null,
            ];
        }

        // Bloco Equipe
        $blocoEquipe = null;
        if (count($equipes)) {
            $questionsById = [];
            foreach ($formGroups as $group) {
                foreach ($group->questions as $question) {
                    $questionsById[$question->id] = $question;
                }
            }

            $allAnswers = [];
            foreach ($equipes as $reqEquipe) {
                $answers = Answer::where('evaluation_id', $reqEquipe->evaluation_id)->get();
                foreach ($answers as $ans) {
                    $allAnswers[$ans->question_id][] = intval($ans->score);
                }
            }

            $answersEquipe = [];
            foreach ($questionsById as $qId => $qObj) {
                $scores = $allAnswers[$qId] ?? [];
                $media = count($scores) ? round(array_sum($scores) / count($scores), 2) : null;
                $answersEquipe[] = [
                    'question' => $qObj->text_content,
                    'score_media' => $media,
                    'weight' => $qObj->weight,
                ];
            }

            $notaEquipe = count($answersEquipe)
                ? round(
                    array_reduce($answersEquipe, fn($carry, $item) => $carry + ($item['score_media'] * $item['weight']), 0) /
                    array_reduce($answersEquipe, fn($carry, $item) => $carry + $item['weight'], 0),
                    2
                )
                : null;

            $blocoEquipe = [
                'tipo' => 'Equipe',
                'nota' => $notaEquipe,
                'answers' => $answersEquipe,
            ];
        }

        // Cálculo final com lógica de nota zerada
        $notaAuto = optional(collect($blocos)->first(fn($b) => str_contains(strtolower($b['tipo']), 'auto')))['nota'] ?? null;
        $notaChefia = optional(collect($blocos)->first(fn($b) => in_array(strtolower($b['tipo']), ['servidor', 'gestor', 'comissionado'])))['nota'] ?? null;
        $notaEquipe = $blocoEquipe ? $blocoEquipe['nota'] : null;

        $isGestor = $notaEquipe !== null;
        if (
            ($isGestor && ($notaAuto === null || $notaChefia === null || $notaEquipe === null)) ||
            (!$isGestor && ($notaAuto === null || $notaChefia === null))
        ) {
            $notaFinal = 0;
            $calcFinal = 'Nota zerada por ausência de preenchimento de uma ou mais partes.';
        } else {
            $notaFinal = $isGestor
                ? round(($notaAuto * 0.25) + ($notaChefia * 0.5) + ($notaEquipe * 0.25), 2)
                : round(($notaAuto * 0.3) + ($notaChefia * 0.7), 2);

            $calcFinal = $isGestor
                ? "($notaAuto x 25%) + ($notaChefia x 50%) + ($notaEquipe x 25%) = $notaFinal"
                : "($notaAuto x 30%) + ($notaChefia x 70%) = $notaFinal";
        }

        return inertia('Dashboard/EvaluationDetail', [
            'year' => $year,
            'person' => $person,
            'form' => $form,
            'blocos' => $blocos,
            'blocoEquipe' => $blocoEquipe,
            'final_score' => $notaFinal,
            'calc_final' => $calcFinal,
            'is_commission' => user_can('recourse'),
        ]);
    }

    public function acknowledge(Request $request, string $year)
    {
        $request->validate([
            'signature_base64' => 'required|string',
        ]);

        $user = Auth::user();
        
        $person = Person::where('cpf', $user->cpf)->first();

        if (!$person) {
            return back()->withErrors(['user' => 'Pessoa vinculada não encontrada.']);
        }

        // Verifica se já existe assinatura para o ano
        $alreadySigned = Acknowledgment::where('person_id', $person->id)
            ->where('year', $year)
            ->exists();
        if ($alreadySigned) {
            return redirect()->route('evaluations')->with('error', 'Você já assinou a avaliação deste ano.');
        }

        Acknowledgment::create([
            'person_id' => $person->id,
            'year' => $year,
            'signed_at' => now(),
            'signature_base64' => $request->input('signature_base64'),
        ]);

        return redirect()->route('evaluations')->with('success', 'Assinatura registrada com sucesso!');
    }


    public function unanswered(Request $request)
    {
        $search = $request->input('search');

        // 1. Define o ano com regra de janeiro/fevereiro
        $currentYear = in_array(date('n'), [1, 2]) ? date('Y') - 1 : date('Y');

        // 2. Tipos de formulários válidos
        $formTypes = ['servidor', 'gestor', 'comissionado']; // adapte se necessário

        // 3. Busca o prazo final via tabela forms
        $form = Form::where('year', $currentYear)
            ->whereIn('type', $formTypes)
            ->where('release', true)
            ->select('term_first', 'term_end')
            ->first();

        // 4. Verifica se o prazo já encerrou
        $prazoFinal = $form?->term_end ? Carbon::parse($form->term_end)->endOfDay() : null;

        if (!$prazoFinal || now()->lessThanOrEqualTo($prazoFinal)) {
            return redirect()->route('evaluations.pending')
                ->with('error', 'As avaliações ainda estão dentro do prazo.');
        }

        // 5. Consulta apenas avaliações pendentes
        $query = EvaluationRequest::with([
            'evaluation.form',
            'evaluation.evaluatedPerson',
            'requestedPerson'
        ])->where('status', 'pending');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('evaluation.evaluatedPerson', fn($q) => $q->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('requestedPerson', fn($q) => $q->where('name', 'like', "%{$search}%"));
            });
        }

        $pendingExpired = $query
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

        return inertia('Evaluations/PendingExpired', [
            'pendingRequests' => $pendingExpired,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

}