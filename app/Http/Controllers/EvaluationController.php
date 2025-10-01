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
use Illuminate\Support\Facades\DB;
use App\Models\EvaluationRequest;
use App\Models\User;
use App\Models\configs as Config; // Importar o model de configurações
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;

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

        // Verificar se a pontuação total é inferior a 70
        $totalScore = collect($data['answers'])->sum('score');
        
        if ($totalScore < 70 && (empty($data['evidencias']) || trim($data['evidencias']) === '')) {
            return back()->withErrors([
                'evidencias' => 'Evidências são obrigatórias para avaliações com pontuação total abaixo de 70.'
            ]);
        }

        DB::beginTransaction();
        try {
            $evaluationRequest = EvaluationRequest::findOrFail($data['evaluation_request_id']);
            $evaluation = $evaluationRequest->evaluation;

            if (!$evaluation) {
                throw new \Exception('Avaliação não encontrada para esta solicitação.');
            }

            // CORREÇÃO: Deletar apenas as respostas do avaliador específico, não todas
            $evaluation->answers()
                ->where('subject_person_id', $evaluationRequest->requested_person_id)
                ->delete();

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
            return back()->withErrors(['error' => 'Erro ao salvar avaliação. O erro foi registrado para análise.']);
        }
    }


    /**
     * Verifica a disponibilidade do formulário de chefia, incluindo a regra de prazo.
     */
    public function checkChefiaFormStatus()
{
    $currentYear = date('Y');
    $now = now();
    $user = auth()->user();

    // 1. Busca o formulário padrão do ano
    $chefiaForm = Form::where('type', 'chefia')
        ->where('year', $currentYear)
        ->first();

    if (!$chefiaForm || !$chefiaForm->release || !$chefiaForm->term_first || !$chefiaForm->term_end) {
        return response()->json([
            'available' => false,
            'message' => 'O formulário de avaliação da chefia não está configurado ou liberado para este ano.'
        ]);
    }

    // 2. Verifica se está dentro do prazo PADRÃO
    $isWithinStandardPeriod = $now->between(
        Carbon::parse($chefiaForm->term_first),
        Carbon::parse($chefiaForm->term_end)->endOfDay()
    );

    // 3. Verifica se o usuário tem um prazo de EXCEÇÃO
    $isWithinExceptionPeriod = false;
    if ($user && $user->cpf) {
        $person = Person::where('cpf', $user->cpf)->first();
        if ($person) {
            $request = EvaluationRequest::where('requested_person_id', $person->id)
                ->whereHas('evaluation', function ($q) {
                    $q->where('type', 'chefia');
                })->first();

            if ($request && $request->exception_date_first && $request->exception_date_end) {
                $isWithinExceptionPeriod = $now->between(
                    Carbon::parse($request->exception_date_first),
                    Carbon::parse($request->exception_date_end)->endOfDay()
                );
            }
        }
    }

    // 4. Se estiver em qualquer um dos prazos, está disponível
    if ($isWithinStandardPeriod || $isWithinExceptionPeriod) {
        return response()->json(['available' => true]);
    }

    // Se não, exibe a mensagem de erro
    $startDate = $chefiaForm->term_first->format('d/m/Y');
    $endDate = $chefiaForm->term_end->format('d/m/Y');
    return response()->json([
        'available' => false,
        'message' => "Fora do prazo. O formulário está disponível para preenchimento apenas entre {$startDate} e {$endDate}."
    ]);
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
    $now = now();
    $user = auth()->user();

    // Busca o formulário padrão do ano
    $autoavaliacaoForm = Form::where('type', 'servidor')
        ->where('year', $currentYear)
        ->first();

    if (!$autoavaliacaoForm || !$autoavaliacaoForm->release || !$autoavaliacaoForm->term_first || !$autoavaliacaoForm->term_end) {
        return response()->json([
            'available' => false,
            'message' => 'O formulário de autoavaliação não está configurado ou liberado para este ano.'
        ]);
    }

    // 1. Verifica se está dentro do prazo PADRÃO
    $isWithinStandardPeriod = $now->between(
        Carbon::parse($autoavaliacaoForm->term_first),
        Carbon::parse($autoavaliacaoForm->term_end)->endOfDay()
    );

    // 2. Verifica se o usuário tem um prazo de EXCEÇÃO
    $isWithinExceptionPeriod = false;
    if ($user && $user->cpf) {
        $person = Person::where('cpf', $user->cpf)->first();
        if ($person) {
            $request = EvaluationRequest::where('requested_person_id', $person->id)
                ->whereHas('evaluation', function ($q) {
                    $q->whereIn('type', ['autoavaliação', 'autoavaliaçãoGestor', 'autoavaliaçãoComissionado']);
                })->first();

            if ($request && $request->exception_date_first && $request->exception_date_end) {
                $isWithinExceptionPeriod = $now->between(
                    Carbon::parse($request->exception_date_first),
                    Carbon::parse($request->exception_date_end)->endOfDay()
                );
            }
        }
    }

    // 3. Se estiver em qualquer um dos prazos, está disponível
    if ($isWithinStandardPeriod || $isWithinExceptionPeriod) {
        return response()->json(['available' => true]);
    }

    // Se não, exibe a mensagem de erro com o prazo padrão
    $startDate = $autoavaliacaoForm->term_first->format('d/m/Y');
    $endDate = $autoavaliacaoForm->term_end->format('d/m/Y');
    return response()->json([
        'available' => false,
        'message' => "Fora do prazo. O formulário está disponível para preenchimento apenas entre {$startDate} e {$endDate}."
    ]);
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
        $typeFilter = $request->input('type');
        $formFilter = $request->input('form');
        $statusFilter = $request->input('status');

        // Busca todos os tipos e formulários disponíveis para os filtros
        $availableTypesQuery = EvaluationRequest::with('evaluation')
            ->whereIn('status', ['completed', 'invalidated'])
            ->whereHas('evaluation', function ($q) {
                $q->whereNotNull('type');
            });

        $availableTypes = $availableTypesQuery
            ->get()
            ->pluck('evaluation.type')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        $availableFormsQuery = EvaluationRequest::with('evaluation.form')
            ->whereIn('status', ['completed', 'invalidated'])
            ->whereHas('evaluation.form', function ($q) {
                $q->whereNotNull('name');
            });

        $availableForms = $availableFormsQuery
            ->get()
            ->pluck('evaluation.form.name')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        // Consulta principal com filtros
        $query = EvaluationRequest::with([
            'evaluation.form',
            'evaluation.evaluatedPerson',
            'requestedPerson'
        ])->whereIn('status', ['completed', 'invalidated']);

        // Aplicar filtro de busca
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('evaluation.evaluatedPerson', function ($subQ) use ($search) {
                    $subQ->where('name', 'like', "%{$search}%");
                })
                    ->orWhereHas('requestedPerson', function ($subQ) use ($search) {
                        $subQ->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Aplicar filtro de tipo
        if ($typeFilter) {
            $query->whereHas('evaluation', function ($q) use ($typeFilter) {
                $q->where('type', $typeFilter);
            });
        }

        // Aplicar filtro de formulário
        if ($formFilter) {
            $query->whereHas('evaluation.form', function ($q) use ($formFilter) {
                $q->where('name', $formFilter);
            });
        }

        // Aplicar filtro de status
        if ($statusFilter) {
            $query->where('status', $statusFilter);
        }

        $completedRequests = $query
            ->orderByDesc('created_at')
            ->paginate(20)
            ->through(function ($request) {
                $form = $request->evaluation->form;
                $canDelete = false;
                $canInvalidate = false;

                if (auth()->user() && user_can('evaluations.completed')) {
                    // Verifica se ainda está dentro do prazo para excluir
                    $config = Config::where('year', $form->year ?? date('Y'))->first();
                    
                    if ($config && $config->gradesPeriod) {
                        // Se já passou da data de divulgação das notas, não pode mais excluir
                        $gradesPeriodDate = \Carbon\Carbon::parse($config->gradesPeriod)->startOfDay();
                        $now = \Carbon\Carbon::now();
                        
                        // Só pode excluir se ainda não chegou na data de divulgação das notas
                        $canDelete = $now->lessThan($gradesPeriodDate);
                    } else {
                        // Se não há data configurada, permite excluir (comportamento padrão)
                        $canDelete = true;
                    }
                }

                // Calcular a nota ponderada da avaliação
                $score = $this->calculateEvaluationScore($request);

                // Permissão para anular: reutiliza mesma permissão de excluir ou pode ser nova (ajustar conforme regra)
                // Só pode anular se ainda está completo (não pode anular uma já anulada)
                if (auth()->user() && user_can('evaluations.completed') && $request->status === 'completed') {
                    $canInvalidate = true;
                }

                return [
                    'id' => $request->id,
                    'type' => $request->evaluation->type ?? '-',
                    'form_name' => $request->evaluation->form->name ?? '-',
                    'avaliado' => $request->evaluation->evaluatedPerson->name ?? '-',
                    'avaliador' => $request->requestedPerson->name ?? '-',
                    'created_at' => $request->created_at?->format('d/m/Y H:i') ?? '',
                    'score' => $score,
                    'status' => $request->status, // Adicionar campo status
                    'can_delete' => $canDelete,
                    'can_invalidate' => $canInvalidate,
                ];
            })

            ->withQueryString();

        return inertia('Evaluations/Completed', [
            'completedRequests' => $completedRequests,
            'filters' => [
                'search' => $search,
                'type' => $typeFilter,
                'form' => $formFilter,
                'status' => $statusFilter,
            ],
            'availableTypes' => $availableTypes,
            'availableForms' => $availableForms,
        ]);
    }

    /**
     * Anula (invalida) uma avaliação concluída, alterando status para 'invalidated'.
     */
    public function invalidateCompleted(Request $request, $id)
    {
        $evaluationRequest = EvaluationRequest::findOrFail($id);

        if ($evaluationRequest->status !== 'completed') {
            return back()->with('error', 'Somente avaliações concluídas podem ser anuladas.');
        }

        if (!user_can('evaluations.completed')) {
            abort(403, 'Sem permissão para anular avaliação.');
        }

        // Validar justificativa
        $request->validate([
            'invalidation_reason' => 'required|string|max:1000',
        ], [
            'invalidation_reason.required' => 'O motivo da anulação é obrigatório.',
            'invalidation_reason.max' => 'O motivo da anulação não pode ter mais de 1000 caracteres.',
        ]);

        // Salvar dados da invalidação
        $evaluationRequest->status = 'invalidated';
        $evaluationRequest->invalidated_by = auth()->id();
        $evaluationRequest->invalidated_at = now();
        $evaluationRequest->invalidation_reason = $request->invalidation_reason;
        $evaluationRequest->save();

        return back()->with('success', 'Avaliação anulada com sucesso.');
    }

    /**
     * Retorna os detalhes da invalidação de uma avaliação anulada.
     */
    public function getInvalidationDetails($id)
    {
        $evaluationRequest = EvaluationRequest::with(['invalidatedBy'])->findOrFail($id);

        if ($evaluationRequest->status !== 'invalidated') {
            return response()->json(['error' => 'Esta avaliação não foi anulada.'], 400);
        }

        return response()->json([
            'invalidated_by' => $evaluationRequest->invalidatedBy ? $evaluationRequest->invalidatedBy->name : 'Usuário não identificado',
            'invalidated_at' => $evaluationRequest->invalidated_at ? $evaluationRequest->invalidated_at->format('d/m/Y H:i') : 'Data não disponível',
            'invalidation_reason' => $evaluationRequest->invalidation_reason ?? 'Motivo não informado',
        ]);
    }

    public function generatePDF($id)
    {
        $evaluationRequest = EvaluationRequest::with([
            'evaluation.form.groupQuestions.questions',
            'evaluation.evaluatedPerson.jobFunction',
            'evaluation.evaluatedPerson.organizationalUnit.allParents',
            'requestedPerson.jobFunction',
            'requestedPerson.organizationalUnit.allParents',
            'evaluation.answers'
        ])->findOrFail($id);


        // Verificar permissões (apenas usuários com permissão específica podem gerar PDF)
        if (!user_can('evaluations.completed.pdf')) {
            abort(403, 'Acesso negado. Apenas administradores e RH podem gerar PDFs.');
        }

        // CORREÇÃO: Buscar apenas as respostas específicas deste avaliador
        $answers = Answer::where('evaluation_id', $evaluationRequest->evaluation_id)
            ->where('subject_person_id', $evaluationRequest->requested_person_id)
            ->get();
        $form = $evaluationRequest->evaluation->form;
        $groupQuestions = $form->groupQuestions ?? [];
        
        $questionAnswers = [];
        $groupedQuestionAnswers = [];
        $totalScore = 0;
        $totalWeight = 0;

        foreach ($groupQuestions as $group) {
            $groupItems = [];
            foreach ($group->questions as $question) {
                $answer = $answers->firstWhere('question_id', $question->id);
                $score = $answer ? (int) $answer->score : null;

                // Flat list kept for backward compatibility (no longer displayed)
                $questionAnswers[] = [
                    'question' => $question->text_content,
                    'score' => $score,
                    'weight' => $question->weight,
                ];

                // Grouped structure for the new PDF layout
                $groupItems[] = [
                    'question' => $question->text_content,
                    'score' => $score,
                ];

                if ($score !== null) {
                    $totalScore += $score * ($question->weight ?? 1);
                    $totalWeight += ($question->weight ?? 1);
                }
            }
            $groupedQuestionAnswers[] = [
                'group' => $group->name,
                'questions' => $groupItems,
            ];
        }
        
        $averageScore = $totalWeight > 0 ? round($totalScore / $totalWeight, 2) : 0;

        $data = [
            'evaluation' => $evaluationRequest->evaluation,
            'evaluatedPerson' => $evaluationRequest->evaluation->evaluatedPerson,
            'evaluatorPerson' => $evaluationRequest->requestedPerson,
            'form' => $form,
            'questionAnswers' => $questionAnswers,
            'groupedQuestionAnswers' => $groupedQuestionAnswers,
            'averageScore' => $averageScore,
            'evidencias' => $evaluationRequest->evidencias,
            'assinatura_base64' => $evaluationRequest->assinatura_base64,
            'completedAt' => $evaluationRequest->updated_at,
        ];

        $pdf = Pdf::loadView('pdf.evaluation-report', $data);
        
        $fileName = sprintf(
            'avaliacao_%s_%s_%s.pdf',
            str_replace(' ', '_', $evaluationRequest->evaluation->evaluatedPerson->name ?? 'avaliado'),
            str_replace(' ', '_', $evaluationRequest->requestedPerson->name ?? 'avaliador'),
            $evaluationRequest->updated_at?->format('Y-m-d') ?? date('Y-m-d')
        );

        return $pdf->download($fileName);
    }

    public function deleteCompleted($id)
    {
        $evaluationRequest = EvaluationRequest::findOrFail($id);

        if (!user_can('evaluations.completed')) {
            abort(403, 'Sem permissão');
        }

        // Verificar se ainda está dentro do prazo para excluir
        $form = $evaluationRequest->evaluation->form;
        $config = Config::where('year', $form->year ?? date('Y'))->first();
        
        if ($config && $config->gradesPeriod) {
            $gradesPeriodDate = \Carbon\Carbon::parse($config->gradesPeriod)->startOfDay();
            $now = \Carbon\Carbon::now();
            
            // Se já passou da data de divulgação das notas, não pode mais excluir
            if ($now->greaterThanOrEqualTo($gradesPeriodDate)) {
                return back()->with('error', 'Não é possível excluir avaliações após a data de divulgação das notas.');
            }
        }

        DB::beginTransaction();
        try {
            // Registra quem fez a ação
            $evaluationRequest->deleted_by = auth()->id();
            $evaluationRequest->deleted_at = now();

            // Volta para status pending
            $evaluationRequest->status = 'pending';
            $evaluationRequest->save();

            // Deleta as respostas associadas específicas deste avaliador
            Answer::where('evaluation_id', $evaluationRequest->evaluation_id)
                ->where('subject_person_id', $evaluationRequest->requested_person_id)
                ->delete();

            // Remove evidências e assinatura
            $evaluationRequest->update([
                'evidencias' => null,
                'assinatura_base64' => null
            ]);

            DB::commit();
            return back()->with('success', 'Avaliação retornada para pendente com sucesso!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Erro ao reverter avaliação.']);
        }
    }

    public function showEvaluationResult(EvaluationRequest $evaluationRequest)
    {
        // Carrega todos os relacionamentos necessários para a tela de resultado
        $evaluationRequest->load([
            'evaluation.form.groupQuestions.questions',
            'evaluation.evaluated.jobFunction', // função/cargo do avaliado
            'evaluation.evaluated.organizationalUnit.allParents'
        ]);

        // CORREÇÃO: Buscar apenas as respostas específicas deste avaliador
        $answers = Answer::where('evaluation_id', $evaluationRequest->evaluation_id)
            ->where('subject_person_id', $evaluationRequest->requested_person_id)
            ->get();

        // Pode ser necessário ajustar para pegar campos default, caso estejam nulos.
        $evaluated = $evaluationRequest->evaluation->evaluated;

        return Inertia::render('Evaluation/AvaliacaoResultadoPage', [
            'form' => $evaluationRequest->evaluation->form,
            'person' => $evaluated,
            'type' => $evaluationRequest->evaluation->type,
            'evaluation' => [
                'answers' => $answers,
                'evidencias' => $evaluationRequest->evidencias,
                'assinatura_base64' => $evaluationRequest->assinatura_base64,
                'updated_at' => $evaluationRequest->updated_at,
            ],
        ]);
    }

    public function myEvaluationsHistory()
    {
        $user = Auth::user();
        $person = Person::with('jobFunction')->where('cpf', $user->cpf)->first();

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
                if (!$request || $request->status !== 'completed')
                    return null;
                $form = $request->evaluation?->form;
                $groups = $form?->groupQuestions ?? [];
                $answers = Answer::where('evaluation_id', $request->evaluation_id)
                    ->where('subject_person_id', $request->requested_person_id)
                    ->get();

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
            $equipes = $requestsAno->filter(
                fn($r) =>
                str_contains(strtolower($r->evaluation->type ?? ''), 'equipe') ||
                (strtolower($r->evaluation->type ?? '') === 'chefia' && $r->requested_person_id !== $person->direct_manager_id)
            );

            // Para verificar se DEVERIA ter avaliação de equipe, buscar TODAS as requisições (incluindo pending)
            $todasRequestsAno = $requests->filter(function ($req) use ($ano) {
                $form = $req->evaluation?->form;
                $year = $form?->year ?? ($form?->period ? \Carbon\Carbon::parse($form->period)->format('Y') : null);
                return $year == $ano;
            });
            
            $todasEquipes = $todasRequestsAno->filter(
                fn($r) =>
                str_contains(strtolower($r->evaluation->type ?? ''), 'equipe') ||
                (strtolower($r->evaluation->type ?? '') === 'chefia' && $r->requested_person_id !== $person->direct_manager_id)
            );

            $notaAuto = $auto ? $getNotaPonderada($auto) : null;
            $notaChefia = $chefia ? $getNotaPonderada($chefia) : null;
            
            // CORREÇÃO: Só calcular nota de equipe se há avaliações COMPLETED
            $equipesCompleted = $equipes->filter(fn($r) => $r->status === 'completed');
            $notaEquipe = $equipesCompleted->count() > 0 ? round($equipesCompleted->avg(fn($r) => $getNotaPonderada($r)), 2) : null;

            $calcAuto = $auto ? "Autoavaliação: $notaAuto" : '';
            $calcChefia = $chefia ? "Chefia: $notaChefia" : '';
            $calcEquipe = $equipesCompleted->count() ? "Equipe (média de {$equipesCompleted->count()} avaliações): $notaEquipe" : '';

            // Determinar se é gestor baseado na função organizacional da pessoa
            $isGestor = $person->jobFunction && $person->jobFunction->is_manager;

            // Definição padrão: se deveria haver avaliação de equipe neste ano (mesmo pendente)
            $deveTeravaliacaoEquipe = $todasEquipes->count() > 0;

            // Lógica da nota final
            if ($isGestor) {
                // Para gestores: todas as três avaliações são obrigatórias
                // Verificar se DEVERIA ter avaliação de equipe (mesmo que pending)
                if ($notaAuto === null || $notaChefia === null || ($deveTeravaliacaoEquipe && $notaEquipe === null)) {
                    $notaFinal = 0;
                    if ($deveTeravaliacaoEquipe && $notaEquipe === null) {
                        $calcFinal = 'Nota zerada por ausência de avaliação de equipe (obrigatória para gestores).';
                    } else {
                        $calcFinal = 'Nota zerada por ausência de autoavaliação ou avaliação de chefia.';
                    }
                } else {
                    // Gestor com todas as avaliações: 25% + 50% + 25%
                    $notaFinal = round(($notaAuto * 0.25) + ($notaChefia * 0.5) + ($notaEquipe * 0.25), 2);
                    $calcFinal = "($notaAuto x 25%) + ($notaChefia x 50%) + ($notaEquipe x 25%) = $notaFinal";
                }
            } else {
                // Para não-gestores: lógica original (autoavaliação + chefia)
                if ($notaAuto === null || $notaChefia === null) {
                    $notaFinal = 0;
                    $calcFinal = 'Nota zerada por ausência de preenchimento de uma ou mais partes.';
                } else {
                    $notaFinal = round(($notaAuto * 0.3) + ($notaChefia * 0.7), 2);
                    $calcFinal = "($notaAuto x 30%) + ($notaChefia x 70%) = $notaFinal";
                }
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

            // Informações sobre avaliação de equipe
            $teamInfo = null;
            if ($deveTeravaliacaoEquipe) {
                $teamInfo = [
                    'total_members' => $todasEquipes->count(),
                    'completed_members' => $equipesCompleted->count(),
                    'has_team_evaluation' => $deveTeravaliacaoEquipe,
                ];
            }

            $evaluations[] = [
                'year' => $ano,
                'user' => $person->name,
                'final_score' => $notaFinal,
                'calc_final' => $calcFinal,
                'calc_auto' => $calcAuto,
                'calc_chefia' => $calcChefia,
                'calc_equipe' => $calcEquipe,
                'team_info' => $teamInfo,
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

        // ✅ Se não for comissão, só pode ver se foi avaliado ou avaliador
        if (!user_can('recourse')) {
            if (!$person || ($evaluationRequest->requester_person_id !== $person->id && $evaluationRequest->evaluation->evaluated_person_id !== $person->id)) {
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

        // Busca TODAS as avaliações da pessoa avaliada no mesmo ano (apenas completed)
        $evaluatedPersonId = $evaluation->evaluated_person_id;
        $evaluatedPerson = Person::with('jobFunction')->find($evaluatedPersonId);
        
        $requestsAno = EvaluationRequest::with([
            'evaluation.form.groupQuestions.questions',
            'requested',
            'requester',
        ])
            ->whereHas('evaluation', function($query) use ($evaluatedPersonId, $year) {
                $query->where('evaluated_person_id', $evaluatedPersonId)
                      ->whereHas('form', function($formQuery) use ($year) {
                          $formQuery->where('year', $year);
                      });
            })
            ->where('status', 'completed')
            ->get();

        // Busca TODAS as requisições (incluindo pending) para verificar se deveria ter avaliação de equipe
        $todasRequestsAno = EvaluationRequest::with([
            'evaluation.form.groupQuestions.questions',
            'requested',
            'requester',
        ])
            ->whereHas('evaluation', function($query) use ($evaluatedPersonId, $year) {
                $query->where('evaluated_person_id', $evaluatedPersonId)
                      ->whereHas('form', function($formQuery) use ($year) {
                          $formQuery->where('year', $year);
                      });
            })
            ->get();

        // Obter o formulário de qualquer uma das avaliações (todos do mesmo ano devem ter formulários similares)
        $formGroups = [];
        if ($requestsAno->isNotEmpty()) {
            $firstForm = $requestsAno->first()->evaluation->form;
            $formGroups = $firstForm->groupQuestions ?? [];
        }

        $blocos = [];
        $equipes = [];

        foreach ($requestsAno as $r) {
            $type = strtolower($r->evaluation->type ?? '');
            
            // Identificar avaliações de equipe
            // Equipe = avaliações do tipo 'chefia' feitas por pessoas que não são o chefe direto
            $isEquipeEvaluation = str_contains($type, 'equipe') || 
                                  ($type === 'chefia' && $r->requested_person_id !== $evaluatedPerson->direct_manager_id);
            
            if ($isEquipeEvaluation) {
                $equipes[] = $r;
                continue;
            }

            $answers = Answer::where('evaluation_id', $r->evaluation_id)
                ->where('subject_person_id', $r->requested_person_id)
                ->get();
            $requestForm = $r->evaluation->form;
            $requestFormGroups = $requestForm->groupQuestions ?? [];
            
            $byQuestion = [];
            $somaNotas = 0;
            $somaPesos = 0;

            foreach ($requestFormGroups as $group) {
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

            $evaluatorName = $r->requestedPerson->name ?? 'Não informado';
            
            // Se é autoavaliação, mostrar como tal
            if (in_array($type, ['autoavaliação', 'autoavaliaçãogestor', 'autoavaliaçãocomissionado'])) {
                $evaluatorName = $r->evaluation->evaluatedPerson->name . ' (Autoavaliação)';
            }

            $blocos[] = [
                'tipo' => $r->evaluation->type ?? '-',
                'nota' => $nota,
                'answers' => $byQuestion,
                'evidencias' => $r->evidencias ?? null,
                'evaluator_name' => $evaluatorName,
            ];
        }

        // Bloco Equipe
        $blocoEquipe = null;
        $teamInfo = null;
        
        // Verificar se deveria ter avaliação de equipe (buscar todas as requisições, não só completed)
        $todasEquipesDetail = $todasRequestsAno->filter(function($r) use ($evaluatedPerson) {
            $type = strtolower($r->evaluation->type ?? '');
            return str_contains($type, 'equipe') ||
                   ($type === 'chefia' && $r->requested_person_id !== $evaluatedPerson->direct_manager_id);
        });
        
        if ($todasEquipesDetail->count() > 0) {
            $teamInfo = [
                'total_members' => $todasEquipesDetail->count(),
                'completed_members' => count($equipes),
                'has_team_evaluation' => true,
            ];
        }
        
        if (count($equipes)) {
            // Usar o formulário da primeira avaliação de equipe para obter as questões
            $firstEquipeForm = $equipes[0]->evaluation->form;
            $equipeFormGroups = $firstEquipeForm->groupQuestions ?? [];
            
            $questionsById = [];
            foreach ($equipeFormGroups as $group) {
                foreach ($group->questions as $question) {
                    $questionsById[$question->id] = $question;
                }
            }

            $allAnswers = [];
            foreach ($equipes as $reqEquipe) {
                $answers = Answer::where('evaluation_id', $reqEquipe->evaluation_id)
                    ->where('subject_person_id', $reqEquipe->requested_person_id)
                    ->get();
                foreach ($answers as $ans) {
                    if ($ans->score !== null) {
                        $allAnswers[$ans->question_id][] = intval($ans->score);
                    }
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

            // Calcular nota da equipe apenas com questões que têm respostas
            $validAnswers = array_filter($answersEquipe, fn($item) => $item['score_media'] !== null);
            $notaEquipe = count($validAnswers)
                ? round(
                    array_reduce($validAnswers, fn($carry, $item) => $carry + ($item['score_media'] * $item['weight']), 0) /
                    array_reduce($validAnswers, fn($carry, $item) => $carry + $item['weight'], 0),
                    2
                )
                : null;

            $blocoEquipe = [
                'tipo' => 'Equipe',
                'nota' => $notaEquipe,
                'answers' => $answersEquipe,
                'team_info' => $teamInfo,
            ];
        } elseif ($teamInfo) {
            // Caso tenha solicitações de equipe mas nenhuma completed
            $blocoEquipe = [
                'tipo' => 'Equipe',
                'nota' => null,
                'answers' => [],
                'team_info' => $teamInfo,
            ];
        }

        // Cálculo final com lógica corrigida
        $notaAuto = optional(collect($blocos)->first(fn($b) => str_contains(strtolower($b['tipo']), 'auto')))['nota'] ?? null;
        $notaChefia = optional(collect($blocos)->first(fn($b) => in_array(strtolower($b['tipo']), ['servidor', 'gestor', 'comissionado'])))['nota'] ?? null;
        $notaEquipe = $blocoEquipe ? $blocoEquipe['nota'] : null;

        // Determinar se é gestor baseado na função organizacional
        $isGestor = $evaluatedPerson->jobFunction && $evaluatedPerson->jobFunction->is_manager;

        // Para gestores, verificar se DEVERIA ter avaliação de equipe (mesmo que pending)
        $deveTeravaliacaoEquipe = false;
        if ($isGestor) {
            $deveTeravaliacaoEquipe = $todasRequestsAno->filter(function($r) use ($evaluatedPerson) {
                $type = strtolower($r->evaluation->type ?? '');
                return str_contains($type, 'equipe') ||
                       ($type === 'chefia' && $r->requested_person_id !== $evaluatedPerson->direct_manager_id);
            })->count() > 0;
        }

        // Lógica da nota final
        if ($isGestor) {
            // Para gestores: todas as três avaliações são obrigatórias
            if ($notaAuto === null || $notaChefia === null || ($deveTeravaliacaoEquipe && $notaEquipe === null)) {
                $notaFinal = 0;
                if ($deveTeravaliacaoEquipe && $notaEquipe === null) {
                    $calcFinal = 'Nota zerada por ausência de avaliação de equipe (obrigatória para gestores).';
                } else {
                    $calcFinal = 'Nota zerada por ausência de autoavaliação ou avaliação de chefia.';
                }
            } else {
                // Gestor com todas as avaliações: 25% + 50% + 25%
                $notaFinal = round(($notaAuto * 0.25) + ($notaChefia * 0.5) + ($notaEquipe * 0.25), 2);
                $calcFinal = "($notaAuto x 25%) + ($notaChefia x 50%) + ($notaEquipe x 25%) = $notaFinal";
            }
        } else {
            // Para não-gestores: lógica original (autoavaliação + chefia)
            if ($notaAuto === null || $notaChefia === null) {
                $notaFinal = 0;
                $calcFinal = 'Nota zerada por ausência de preenchimento de uma ou mais partes.';
            } else {
                $notaFinal = round(($notaAuto * 0.3) + ($notaChefia * 0.7), 2);
                $calcFinal = "($notaAuto x 30%) + ($notaChefia x 70%) = $notaFinal";
            }
        }

        return inertia('Dashboard/EvaluationDetail', [
            'year' => $year,
            'person' => $person,
            'form' => $form,
            'blocos' => $blocos,
            'blocoEquipe' => $blocoEquipe,
            'final_score' => $notaFinal,
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
        $typeFilter = $request->input('type');
        $formFilter = $request->input('form');

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

        // 5. Busca todos os tipos e formulários disponíveis para os filtros
        $availableTypesQuery = EvaluationRequest::with('evaluation')
            ->where('status', 'pending')
            ->whereHas('evaluation', function ($q) {
                $q->whereNotNull('type');
            });

        $availableTypes = $availableTypesQuery
            ->get()
            ->pluck('evaluation.type')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        $availableFormsQuery = EvaluationRequest::with('evaluation.form')
            ->where('status', 'pending')
            ->whereHas('evaluation.form', function ($q) {
                $q->whereNotNull('name');
            });

        $availableForms = $availableFormsQuery
            ->get()
            ->pluck('evaluation.form.name')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        // 6. Consulta principal com filtros
        $query = EvaluationRequest::with([
            'evaluation.form',
            'evaluation.evaluatedPerson',
            'requestedPerson',
            'releasedByUser'
        ])->where('status', 'pending');

        // Aplicar filtro de busca
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('evaluation.evaluatedPerson', function ($subQ) use ($search) {
                    $subQ->where('name', 'like', "%{$search}%");
                })
                    ->orWhereHas('requestedPerson', function ($subQ) use ($search) {
                        $subQ->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Aplicar filtro de tipo
        if ($typeFilter) {
            $query->whereHas('evaluation', function ($q) use ($typeFilter) {
                $q->where('type', $typeFilter);
            });
        }

        // Aplicar filtro de formulário
        if ($formFilter) {
            $query->whereHas('evaluation.form', function ($q) use ($formFilter) {
                $q->where('name', $formFilter);
            });
        }

        $gradesPeriod = Config::where('year', $currentYear)
            ->value('gradesPeriod');
        $now = Carbon::now();
        $canRelease = false;

        if ($gradesPeriod) {
            // Garante que a comparação seja feita contra o início do dia (00:00:00)
            $gradesPeriodDate = Carbon::parse($gradesPeriod)->startOfDay();

            // Define $canRelease como true se a data atual for estritamente MENOR que a data limite
            $canRelease = $now->lessThan($gradesPeriodDate);
        } else {
            // Se não há 'gradesPeriod' definido, permite a ação por padrão
            $canRelease = true;
        }

        // 7. Consulta as avaliações pendentes que expiraram
        if ($gradesPeriod) {
            $query->where('created_at', '<', Carbon::parse($gradesPeriod)->startOfDay());
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
                    'is_released' => !is_null($request->exception_date_first) && !is_null($request->exception_date_end),
                    'exception_date_first' => $request->exception_date_first ? Carbon::parse($request->exception_date_first)->format('d/m/Y') : null,
                    'exception_date_end' => $request->exception_date_end ? Carbon::parse($request->exception_date_end)->format('d/m/Y') : null,
                    'released_by_name' => $request->releasedByUser->name ?? null,
                ];
            })
            ->withQueryString();

        return inertia('Evaluations/PendingExpired', [
            'pendingRequests' => $pendingExpired,
            'filters' => [
                'search' => $search,
                'type' => $typeFilter,
                'form' => $formFilter,
            ],
            'availableTypes' => $availableTypes,
            'availableForms' => $availableForms,
            'canRelease' => $canRelease,
        ]);
    }

    public function release(Request $request)
    {
        $data = $request->validate([
            'requestId' => 'required|exists:evaluation_requests,id',
            'exceptionDateFirst' => 'required|date',
            'exceptionDateEnd' => 'required|date|after_or_equal:exceptionDateFirst',
            'evaluationType' => 'nullable|string',
        ]);

        $evaluationRequest = EvaluationRequest::findOrFail($data['requestId']);
        $exceptionDateEnd = Carbon::parse($data['exceptionDateEnd']);
        $evaluationType = strtolower($data['evaluationType'] ?? $evaluationRequest->evaluation->type);

        // Busca a configuração do ano atual para obter a data de divulgação das notas
        $config = Config::where('year', date('Y'))->first();
        $gradesPeriodDate = $config ? Carbon::parse($config->gradesPeriod)->startOfDay() : null;

        // Se não houver data de divulgação de notas, não é possível fazer a validação.
        // Você pode decidir o que fazer neste caso, como retornar um erro.
        if (!$gradesPeriodDate) {
            return back()->with('error', 'A data de divulgação das notas não está configurada para o ano atual.');
        }

        // Condição principal para liberar a avaliação
        // 1. Permite liberar qualquer tipo de avaliação se o fim do novo prazo for ANTES da data de divulgação.
        // 2. Se o fim do novo prazo for IGUAL OU DEPOIS da data de divulgação, só permite se for uma autoavaliação.
        $allowedToRelease = false;

        if ($exceptionDateEnd->lessThan($gradesPeriodDate)) {
            // Se a data final da exceção é anterior à data de divulgação das notas, libera para qualquer tipo.
            $allowedToRelease = true;
        } else {
            // Se a data final da exceção é posterior ou igual à data de divulgação,
            // só libera se for um dos tipos de autoavaliação.
            $autoEvaluationTypes = ['autoavaliação', 'autoavaliaçãogestor', 'autoavaliaçãocomissionado'];
            if (in_array($evaluationType, $autoEvaluationTypes)) {
                $allowedToRelease = true;
            }
        }


        if (!$allowedToRelease) {
            return back()->with('error', 'Não é possível liberar esta avaliação com o prazo selecionado. Fora da política de liberação.');
        } else {
            // Atualiza a solicitação de avaliação com as datas de exceção e o responsável pela liberação
            $evaluationRequest->update([
                'exception_date_first' => $data['exceptionDateFirst'],
                'exception_date_end' => $data['exceptionDateEnd'],
                'released_by' => auth()->id(),
            ]);

            return back()->with('success', 'Avaliação liberada com sucesso!');
        }
    }

    /**
     * Calcula a nota ponderada de uma avaliação concluída
     */
    private function calculateEvaluationScore($evaluationRequest)
    {
        try {
            // Para avaliações de equipe, precisamos filtrar por subject_person_id
            // Para outras avaliações, usamos todas as respostas da evaluation
            $answersQuery = Answer::where('evaluation_id', $evaluationRequest->evaluation_id)
                ->with('question');
            
            // Se é uma avaliação de equipe (tipo 'chefia' e há subject_person_id)
            $isTeamEvaluation = strtolower($evaluationRequest->evaluation->type ?? '') === 'chefia';
            
            if ($isTeamEvaluation && $evaluationRequest->requested_person_id) {
                // Para avaliações de equipe, filtra pelas respostas específicas do avaliador
                $answersQuery->where('subject_person_id', $evaluationRequest->requested_person_id);
            }
            
            $answers = $answersQuery->get();

            if ($answers->isEmpty()) {
                return '-';
            }

            $somaNotas = 0;
            $somaPesos = 0;

            foreach ($answers as $answer) {
                if ($answer->question && $answer->score !== null) {
                    $peso = $answer->question->weight ?? 1;
                    $somaNotas += intval($answer->score) * $peso;
                    $somaPesos += $peso;
                }
            }

            return $somaPesos > 0 ? round($somaNotas / $somaPesos, 1) : '-';
        } catch (\Exception $e) {
            return '-';
        }
    }
}