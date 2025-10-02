<?php

namespace App\Http\Controllers;

use App\Models\Evaluation;
use App\Models\EvaluationRecourse;
use App\Models\EvaluationRecourseAssignee;
use App\Models\EvaluationRecourseAttachment;
use App\Models\EvaluationRecourseResponseAttachment;
use App\Models\EvaluationRequest;
use App\Models\Person;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EvaluationRecourseController extends Controller
{
    // Helper to consistently read stage with workflow_stage fallback
    private function getStage(EvaluationRecourse $recourse): ?string
    {
        return $recourse->workflow_stage ?? $recourse->stage ?? null;
    }
    private function hasRole(string $roleName): bool
    {
        $user = Auth::user();
        return $user && $user->roles->pluck('name')->contains($roleName);
    }

    /**
     * Verifica se o usuário atual é do RH (tem permissão total)
     */
    private function isRH(): bool
    {
        return user_can('recourse');
    }

    /**
     * Verifica se o usuário atual é membro da comissão para um recurso específico
     */
    private function isResponsibleForRecourse(EvaluationRecourse $recourse): bool
    {
        $user = Auth::user();
        $person = Person::where('cpf', $user->cpf)->first();
        
        return $person && $recourse->responsiblePersons()->where('person_id', $person->id)->exists();
    }

    /**
     * Verifica se o usuário pode acessar um recurso específico
     */
    private function canAccessRecourse(EvaluationRecourse $recourse): bool
    {
        // RH pode visualizar em qualquer etapa; decisões são validadas em cada ação específica
        if ($this->isRH()) {
            return true;
        }
        // DGP pode acessar quando estiver na etapa da DGP
        $stage = $this->getStage($recourse);
        if ($stage === 'dgp_review' && (user_can('recourses.dgpDecision') || $this->hasRole('DGP') || $this->hasRole('Diretor RH'))) {
            return true;
        }
        // Secretário pode acessar quando estiver na etapa do Secretário
        if ($stage === 'secretary_review' && (user_can('recourses.secretaryDecision') || $this->hasRole('Secretário') || $this->hasRole('Secretaria') || $this->hasRole('Secretario Gestão'))) {
            return true;
        }
        // Comissão só acessa quando a instância é Comissão e a etapa é de análise da Comissão
        if ($recourse->current_instance === 'Comissao' && $stage === 'commission_analysis') {
            return $this->isResponsibleForRecourse($recourse);
        }
        return false;
    }

    /**
     * Exibe todas as avaliações de uma pessoa para análise completa do recurso
     */
    public function viewPersonEvaluations(EvaluationRecourse $recourse)
    {
        if (!$this->canAccessRecourse($recourse)) {
            abort(403, 'Acesso negado.');
        }

        $recourse->load([
            'evaluation.evaluation.evaluatedPerson',
            'evaluation.evaluation.form',
            'person'
        ]);

        $evaluatedPersonId = $recourse->evaluation->evaluation->evaluated_person_id;
        $formId = $recourse->evaluation->evaluation->form_id;

        // Busca todas as avaliações desta pessoa para o ano corrente (incluindo formulários relacionados)
        $currentYear = $recourse->evaluation->evaluation->form->year;
        
        $allEvaluations = Evaluation::where('evaluated_person_id', $evaluatedPersonId)
            ->whereHas('form', function($query) use ($currentYear) {
                $query->where('year', $currentYear);
            })
            ->with([
                'answers' => function($query) {
                    $query->with('question');
                },
                'form.groupQuestions.questions',
                'evaluatedPerson'
            ])
            ->orderByRaw("
                CASE type 
                    WHEN 'auto' THEN 1
                    WHEN 'gestor' THEN 2
                    WHEN 'comissionado' THEN 3
                    WHEN 'servidor' THEN 4
                    WHEN 'par' THEN 5
                    WHEN 'chefia' THEN 6
                    ELSE 7
                END
            ")
            ->get();

        // Busca os avaliadores para todas as avaliações de uma vez
        $evaluationIds = $allEvaluations->pluck('id');
        $evaluationRequests = EvaluationRequest::whereIn('evaluation_id', $evaluationIds)
            ->with(['requester', 'requested'])
            ->get()
            ->groupBy('evaluation_id');

        return inertia('Recourses/PersonEvaluations', [
            'recourse' => [
                'id' => $recourse->id,
                'status' => $recourse->status,
                'person' => [
                    'name' => $recourse->person->name,
                ],
                'evaluation' => [
                    'year' => optional($recourse->evaluation->evaluation->form)->year ?? '—',
                    'form_name' => $recourse->evaluation->evaluation->form->name ?? '—',
                ]
            ],
            'evaluations' => $allEvaluations->map(function ($evaluation) use ($evaluationRequests) {
                $answers = $evaluation->answers;
                // Modificado: incluir score = 0 como válido
                $validScores = $answers->whereNotNull('score')->pluck('score');
                
                // Busca as solicitações de avaliação para esta evaluation
                $requests = $evaluationRequests->get($evaluation->id, collect());
                
                $evaluatorName = 'Sistema';
                $average = $validScores->count() > 0 ? round($validScores->avg(), 1) : null;
                
                // Calcula o total score considerando os pesos das perguntas
                $totalScore = 0;
                $totalWeight = 0;
                
                if ($evaluation->form && $evaluation->form->groupQuestions) {
                    foreach ($evaluation->form->groupQuestions as $group) {
                        foreach ($group->questions as $question) {
                            $answer = $answers->firstWhere('question_id', $question->id);
                            $score = $answer ? $answer->score : null;
                            $weight = $question->weight ?? 1;
                            
                            // Modificado: incluir score = 0 como válido
                            if ($score !== null && !is_null($weight)) {
                                $totalScore += $score * $weight;
                                $totalWeight += $weight;
                            }
                        }
                    }
                }
                
                // Se houver pesos, calcula a média ponderada; senão, usa a soma simples
                if ($totalWeight > 0) {
                    $totalScore = round($totalScore / $totalWeight);
                } else {
                    $totalScore = $validScores->sum();
                }
                
                // Verifica se é avaliação de chefia (pode ser individual ou de equipe)
                $isChefiaType = strtolower($evaluation->type ?? '') === 'chefia';
                $isTeamEvaluation = $isChefiaType && $requests->count() > 0; // Chefia sempre pode ser equipe
                
                
                if ($isTeamEvaluation) {
                    // Para avaliações de chefia/equipe
                    $teamMembers = collect();
                    
                    // Coleta informações dos membros da equipe
                    foreach ($requests as $request) {
                        if ($request->requested) {
                            $teamMembers->push($request->requested->name);
                        }
                    }
                    
                    // Se há membros da equipe, mostra como avaliação de equipe
                    if ($teamMembers->count() > 0) {
                        $evaluatorName = "Equipe de " . $teamMembers->count() . " membro(s)";
                    } else {
                        $evaluatorName = "Avaliação da Chefia";
                    }
                    
                } else {
                    // Para avaliações individuais
                    $request = $requests->first();
                    
                    if ($request) {
                        if ($evaluation->type === 'auto') {
                            $evaluatorName = $request->requested->name . ' (Autoavaliação)';
                        } else {
                            // Inverte a lógica: mostra o requested (avaliado) como avaliador
                            $evaluatorName = $request->requested->name ?? 'Sistema';
                        }
                    } elseif ($evaluation->type === 'auto') {
                        $evaluatorName = $evaluation->evaluatedPerson->name . ' (Autoavaliação)';
                    }
                }
                
                return [
                    'id' => $evaluation->id,
                    'type' => $evaluation->type,
                    'evaluator_name' => $evaluatorName,
                    'is_team_evaluation' => $isTeamEvaluation,
                    'team_members_count' => $isTeamEvaluation ? $requests->count() : null,
                    'evidencias' => $isTeamEvaluation ? 
                        null :  // Não mostrar evidências para avaliações de equipe
                        ($requests->first()?->evidencias ?? ''), // Sempre retornar string, mesmo vazia
                    'answers' => $answers->map(fn($a) => [
                        'question' => $a->question->text ?? '',
                        'score' => $a->score,
                    ]),
                    'average' => $average,
                    'total_score' => $totalScore,
                    'total_questions' => $answers->count(),
                    'answered_questions' => $validScores->count(),
                ];
            }),
            'media_geral' => $this->calculateMediaGeral($allEvaluations, $evaluationRequests, $recourse->status),
        ]);
    }

    private function calculateMediaGeral($allEvaluations, $evaluationRequests, $recursoStatus = null)
    {
        $chefeAvg = null;
        $equipeAvg = null;
        $autoAvg = null;

        foreach ($allEvaluations as $evaluation) {
            $answers = $evaluation->answers;
            $validScores = $answers->whereNotNull('score')->pluck('score');
            $requests = $evaluationRequests->get($evaluation->id, collect());
            
            if ($validScores->count() > 0) {
                $average = round($validScores->avg(), 1);
                
                if (in_array($evaluation->type, ['gestor', 'comissionado'])) {
                    $chefeAvg = $average;
                } elseif ($evaluation->type === 'chefia' && $requests->count() > 0) {
                    $equipeAvg = $average;
                } elseif (in_array($evaluation->type, ['auto', 'autoavaliaçãoGestor', 'autoavaliaçãoComissionado'])) {
                    $autoAvg = $average;
                }
            }
        }

        // Se recurso foi DEFERIDO (status = 'respondido'), anula a nota do chefe
        $isDeferido = $recursoStatus === 'respondido';
        
        if ($isDeferido) {
            $chefeAvg = null; // Anula a nota do chefe
        }

        // Calcular média geral com pesos (considerando deferimento)
        if ($isDeferido) {
            // Recurso DEFERIDO: considera apenas autoavaliação e equipe (se houver)
            if ($autoAvg !== null) {
                if ($equipeAvg !== null) {
                    // Tem equipe: 75% auto + 25% equipe
                    return round(($autoAvg * 0.75) + ($equipeAvg * 0.25), 1);
                } else {
                    // Sem equipe: 100% auto
                    return $autoAvg;
                }
            }
        } else {
            // Recurso NÃO deferido: lógica normal
            if ($chefeAvg !== null && $autoAvg !== null) {
                if ($equipeAvg !== null) {
                    // Tem equipe: 50% chefe + 25% equipe + 25% auto
                    return round(($chefeAvg * 0.5) + ($equipeAvg * 0.25) + ($autoAvg * 0.25), 1);
                } else {
                    // Sem equipe: 70% chefe + 30% auto
                    return round(($chefeAvg * 0.7) + ($autoAvg * 0.3), 1);
                }
            }
        }

        return null;
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $person = Person::where('cpf', $user->cpf)->first();
        
        $isRH = user_can('recourse');
        
            if (!user_can('recourses.index')) {
                abort(403);
            }
        // PRIORIDADE: Se tem role "Comissão", trata como Comissão mesmo que tenha permissão RH
    $isComissao = $user && $user->roles->pluck('name')->contains('Comissão');
    $isDgp = user_can('recourses.dgpDecision') || $this->hasRole('DGP') || $this->hasRole('Diretor RH');
    $isSecretary = user_can('recourses.secretaryDecision') || $this->hasRole('Secretário') || $this->hasRole('Secretaria') || $this->hasRole('Secretario Gestão');
        
        // Para RH (que não é Comissão), status padrão é 'aberto'. Para Comissão, sem filtro padrão (todos os status)
        $status = $request->get('status');
        if (!$status && $isRH && !$isComissao) {
            $status = 'aberto'; // Apenas RH puro vê recursos abertos por padrão
        }

        $query = EvaluationRecourse::with([
            'person',
            'responsiblePersons',
        ]);

        // Sempre filtrar por instância atual do usuário
        if ($isRH && !$isComissao && !$isDgp && !$isSecretary) {
            // RH pode visualizar recursos independentemente da instância atual
        } else {
            if (!$person) {
                return redirect()->route('dashboard')->with('error', 'Dados de pessoa não encontrados.');
            }
            
            if ($isComissao) {
                // Comissão: recursos atribuídos a mim e na instância Comissão
                $query->whereHas('responsiblePersons', function($q) use ($person) {
                    $q->where('person_id', $person->id);
                })->where('current_instance', 'Comissao');
            } elseif ($isDgp) {
                // DGP: itens na etapa dgp_review
                $query->where(function($q){
                    $q->where('workflow_stage', 'dgp_review')
                      ->orWhere(function($q2){ $q2->whereNull('workflow_stage')->where('stage', 'dgp_review'); });
                });
            } elseif ($isSecretary) {
                // Secretário: itens na etapa secretary_review
                $query->where(function($q){
                    $q->where('workflow_stage', 'secretary_review')
                      ->orWhere(function($q2){ $q2->whereNull('workflow_stage')->where('stage', 'secretary_review'); });
                });
            }
        }

        // Para visões baseadas em etapa (DGP e Secretário), ignoramos filtros de 'status'
        $isStageOnlyView = $isDgp || $isSecretary;

        $recourses = $query
            ->when(!$isStageOnlyView && $status === 'devolvidos', function($q) {
                $q->whereNotNull('last_returned_at')->where('current_instance', 'RH');
            })
            ->when(!$isStageOnlyView && $status && $status !== 'devolvidos', fn($q) => $q->where('status', $status))
            ->latest()
            ->paginate(10)
            ->through(function ($recourse) {
                return [
                    'id' => $recourse->id,
                    'status' => $recourse->status,
                    'text' => $recourse->text,
                    'person' => [
                        'name' => $recourse->person->name ?? '—',
                    ],
                    'evaluation' => [
                        'id' => $recourse->evaluation->id ?? null,
                        'year' => '—', // Temporariamente removido o relacionamento aninhado
                    ],
                    'last_return' => $recourse->lastReturnedBy ? [
                        'by' => $recourse->lastReturnedBy->name,
                        'to' => $recourse->last_returned_to_instance,
                        'at' => optional($recourse->last_returned_at)?->format('d/m/Y H:i'),
                    ] : null,
                    'responsiblePersons' => $recourse->responsiblePersons->map(fn($p) => [
                        'name' => $p->name,
                        'registration_number' => $p->registration_number,
                    ]),
                ];
            })
            ->withQueryString();

        return inertia('Recourses/Index', [
            'recourses' => $recourses,
            'status' => $status ?? 'todos', // Para mostrar no título
            'canManageAssignees' => $isRH && !$isComissao, // Apenas RH puro pode gerenciar responsáveis
            'userRole' => $isComissao ? 'Comissão' : ($isRH ? 'RH' : 'Sem permissão'), // Para debug/informação
        ]);
    }

    public function create($evaluationId)
    {
        $evaluation = EvaluationRequest::with('requestedPerson')->findOrFail($evaluationId);

        return inertia('Recourses/Create', [
            'evaluation' => [
                'id' => $evaluation->id,
                'year' => $evaluation->form->year ?? null,
                'person' => $evaluation->requestedPerson->name,
            ],
        ]);
    }

    public function store(Request $request, $evaluationId)
    {
        $request->validate([
            'text' => 'required|string',
            'attachments.*' => 'file|max:10240',
        ]);

        $user = Auth::user();
        $person = Person::where('cpf', $user->cpf)->first();

        $recourse = EvaluationRecourse::create([
            'evaluation_id' => $evaluationId,
            'person_id' => $person->id,
            'text' => $request->text,
            'status' => 'aberto',
            'current_instance' => 'RH',
            'workflow_stage' => 'rh_analysis',
        ]);

        $recourse->logs()->create([
            'status' => 'aberto',
            'message' => 'Recurso enviado pelo servidor.',
            'created_at' => now(), // ✅ necessário se $timestamps = false
        ]);

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('recourses', 'public');
                EvaluationRecourseAttachment::create([
                    'recourse_id' => $recourse->id,
                    'file_path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                ]);
            }
        }

        return redirect()->route('evaluations.history')->with('success', 'Recurso salvo com sucesso!');
    }

    public function show(EvaluationRecourse $recourse)
    {
        $recourse->load([
            'evaluation.evaluation.form',
            'attachments',
            'responseAttachments',
            'person',
            'user',
            'lastReturnedBy',
            'logs' => fn($q) => $q->orderBy('created_at'),
        ]);

        // Calcular nota final com base nas avaliações do ano do formulário
        // Somente exibida ao usuário após decisão da DGP (homologado ou não)
        $finalScore = null;
        try {
            $evaluatedPersonId = $recourse->evaluation->evaluation->evaluated_person_id ?? null;
            $formYear = optional($recourse->evaluation->evaluation->form)->year;
            if ($evaluatedPersonId && $formYear) {
                $allEvaluations = Evaluation::where('evaluated_person_id', $evaluatedPersonId)
                    ->whereHas('form', function($query) use ($formYear) {
                        $query->where('year', $formYear);
                    })
                    ->with([
                        'answers' => function($query) {
                            $query->with('question');
                        },
                        'form.groupQuestions.questions',
                        'evaluatedPerson'
                    ])
                    ->get();

                $evaluationIds = $allEvaluations->pluck('id');
                $evaluationRequests = EvaluationRequest::whereIn('evaluation_id', $evaluationIds)
                    ->with(['requester', 'requested'])
                    ->get()
                    ->groupBy('evaluation_id');

                // Determina o status efetivo para cálculo conforme decisão DGP/Secretário
                // homologado => tratar como 'respondido' (deferido) para anular chefe; nao_homologado => tratar como 'indeferido'
                $statusForScore = $recourse->status; // padrão: decisão da comissão
                if (!empty($recourse->secretary_decision)) {
                    $statusForScore = $recourse->secretary_decision === 'homologado' ? 'respondido' : 'indeferido';
                } elseif (!empty($recourse->dgp_decision)) {
                    $statusForScore = $recourse->dgp_decision === 'homologado' ? 'respondido' : 'indeferido';
                }

                // Usa a mesma regra de cálculo aplicada na análise completa
                $finalScore = $this->calculateMediaGeral($allEvaluations, $evaluationRequests, $statusForScore);
            }
        } catch (\Throwable $e) {
            // Evita quebrar a tela em caso de dados inconsistentes
            $finalScore = null;
        }

        return inertia('Recourses/Show', [
            'recourse' => [
                'id' => $recourse->id,
                'text' => $recourse->text,
                'status' => $recourse->status,
                'current_instance' => $recourse->current_instance,
                'stage' => $this->getStage($recourse),
                'response' => $recourse->response,
                'responded_at' => optional($recourse->responded_at)?->format('Y-m-d'),
                // Extended workflow fields
                'dgp' => [
                    'decision' => $recourse->dgp_decision,
                    'decided_at' => optional($recourse->dgp_decided_at)?->format('Y-m-d H:i'),
                    'notes' => $recourse->dgp_notes,
                ],
                // Nota final após decisão (DGP ou Secretário)
                'final_score' => ($recourse->dgp_decision || $recourse->secretary_decision) ? $finalScore : null,
                'first_ack_at' => optional($recourse->first_ack_at)?->format('Y-m-d H:i'),
                'second_instance' => [
                    'enabled' => (bool) $recourse->is_second_instance,
                    'requested_at' => optional($recourse->second_instance_requested_at)?->format('Y-m-d H:i'),
                    'text' => $recourse->second_instance_text,
                ],
                'secretary' => [
                    'decision' => $recourse->secretary_decision,
                    'decided_at' => optional($recourse->secretary_decided_at)?->format('Y-m-d H:i'),
                    'notes' => $recourse->secretary_notes,
                ],
                'second_ack_at' => optional($recourse->second_ack_at)?->format('Y-m-d H:i'),
                'last_return' => $recourse->lastReturnedBy ? [
                    'by' => $recourse->lastReturnedBy->name,
                    'to' => $recourse->last_returned_to_instance,
                    'at' => optional($recourse->last_returned_at)?->format('d/m/Y H:i'),
                ] : null,
                'attachments' => $recourse->attachments->map(fn($a) => [
                    'name' => $a->original_name,
                    'url' => Storage::url($a->file_path),
                ]),
                'responseAttachments' => $recourse->responseAttachments->map(fn($a) => [
                    'name' => $a->original_name,
                    'url' => Storage::url($a->file_path),
                ]),
                'evaluation' => [
                    'id' => $recourse->evaluation->evaluation->id,
                    'year' => optional($recourse->evaluation->evaluation->form)->year ?? '—',
                ],
                'person' => [
                    'name' => $recourse->person->name,
                ],
                'logs' => $recourse->logs->map(fn($log) => [
                    'status' => $log->status,
                    'message' => $log->message,
                    'created_at' => $log->created_at->format('d/m/Y H:i'),
                ]),
                // Actions allowed for current user on Show page (typically servidor)
                'actions' => (function () use ($recourse) {
                    $user = Auth::user();
                    $person = $user ? Person::where('cpf', $user->cpf)->first() : null;
                    $isOwner = $person && $person->id === $recourse->person_id;
                    $stage = $this->getStage($recourse);
                    return [
                        'canAcknowledgeFirst' => $isOwner && $stage === 'await_first_ack' && is_null($recourse->first_ack_at) && user_can('recourses.acknowledgeFirst'),
                        'canRequestSecondInstance' => $isOwner && $stage === 'first_ack_done' && !$recourse->is_second_instance && user_can('recourses.requestSecondInstance'),
                        'canAcknowledgeSecond' => $isOwner && $stage === 'await_second_ack' && is_null($recourse->second_ack_at) && user_can('recourses.acknowledgeSecond'),
                    ];
                })(),
            ],
        ]);
    }

    public function review(EvaluationRecourse $recourse)
    {
        // Verifica se a pessoa tem permissão (RH ou é responsável pelo recurso)
        if (!user_can('recourses.review')) {
            return redirect()->route('dashboard')->with('error', 'Você não tem permissão para revisar recursos.');
        }
        if (!$this->canAccessRecourse($recourse)) {
            return redirect()->route('dashboard')->with('error', 'Você não tem permissão para acessar este recurso.');
        }

        $recourse->load([
            'evaluation.evaluation.form',
            'evaluation.evaluation.evaluatedPerson',
            'evaluation.evaluation.answers.question',
            'attachments',
            'responseAttachments',
            'responsiblePersons',
            'person',
            'logs' => fn($q) => $q->orderBy('created_at'),
        ]);

        // Pega o ID da pessoa que está sendo avaliada e busca a avaliação do chefe
        $evaluatedPersonId = $recourse->evaluation->evaluation->evaluatedPerson->id;
        $formId = $recourse->evaluation->evaluation->form_id;
        
        // Busca a avaliação do chefe (pode ser gestor, comissionado ou servidor)
        $chefEvaluation = Evaluation::where('evaluated_person_id', $evaluatedPersonId)
            ->where('form_id', $formId)
            ->whereIn('type', ['gestor', 'comissionado', 'servidor'])
            ->with(['answers.question', 'form', 'evaluatedPerson'])
            ->first();

        // Se não encontrar nenhuma avaliação do chefe, usa a avaliação original
        $evaluationToShow = $chefEvaluation ?? $recourse->evaluation->evaluation;

        // Busca apenas pessoas com role "Comissão" para poder atribuir responsáveis (apenas RH puro)
        $user = Auth::user();
        $isRH = $this->isRH();
        $isComissao = $user && $user->roles->pluck('name')->contains('Comissão');
        $canManageAssignees = $isRH && !$isComissao; // Apenas RH puro pode gerenciar
        
        $availablePersons = $canManageAssignees 
            ? Person::whereIn('cpf', function ($query) {
                    $query->select('cpf')
                        ->from('users')
                        ->whereExists(function ($subQuery) {
                            $subQuery->select('*')
                                ->from('role_user')
                                ->join('roles', 'roles.id', '=', 'role_user.role_id')
                                ->whereColumn('role_user.user_id', 'users.id')
                                ->where('roles.name', 'Comissão');
                        });
                })
                ->select('id', 'name', 'registration_number')
                ->orderBy('name')
                ->get()
            : collect();

        return inertia('Recourses/Review', [
            'recourse' => [
                'id' => $recourse->id,
                'status' => $recourse->status,
                'current_instance' => $recourse->current_instance,
                'stage' => $this->getStage($recourse),
                'text' => $recourse->text,
                'response' => $recourse->response,
                // Extended workflow fields for UI
                'dgp' => [
                    'decision' => $recourse->dgp_decision,
                    'decided_at' => optional($recourse->dgp_decided_at)?->format('Y-m-d H:i'),
                    'notes' => $recourse->dgp_notes,
                ],
                'first_ack_at' => optional($recourse->first_ack_at)?->format('Y-m-d H:i'),
                'second_instance' => [
                    'enabled' => (bool) $recourse->is_second_instance,
                    'requested_at' => optional($recourse->second_instance_requested_at)?->format('Y-m-d H:i'),
                    'text' => $recourse->second_instance_text,
                ],
                'secretary' => [
                    'decision' => $recourse->secretary_decision,
                    'decided_at' => optional($recourse->secretary_decided_at)?->format('Y-m-d H:i'),
                    'notes' => $recourse->secretary_notes,
                ],
                'second_ack_at' => optional($recourse->second_ack_at)?->format('Y-m-d H:i'),
                // Actions for UI controls on Review page
                'actions' => (function () use ($recourse) {
                    $user = Auth::user();
                    $isComissao = $user && $user->roles->pluck('name')->contains('Comissão');
                    $isDgp = user_can('recourses.dgpDecision') || $this->hasRole('Diretor RH') || $this->hasRole('DGP');
                    $isSecretary = user_can('recourses.secretaryDecision') || $this->hasRole('Secretario Gestão') || $this->hasRole('Secretário') || $this->hasRole('Secretaria');
                    $isRH = $this->isRH() && !$isComissao && !$isDgp && !$isSecretary; // RH puro
                    $forwardReasons = [];
                    if (!$isRH) $forwardReasons[] = 'Apenas RH pode encaminhar.';
                    if ($recourse->current_instance !== 'RH') $forwardReasons[] = 'Recurso não está na instância do RH.';
                    if (($recourse->responsiblePersons?->count() ?? 0) === 0) $forwardReasons[] = 'Atribua pelo menos um membro da Comissão como responsável.';
                    if (!user_can('recourses.forwardToCommission')) $forwardReasons[] = 'Usuário sem permissão para encaminhar.';
                    $stage = $this->getStage($recourse);
                    return [
                        'canForwardToCommission' => $isRH && $recourse->current_instance === 'RH' && ($recourse->responsiblePersons?->count() ?? 0) > 0 && user_can('recourses.forwardToCommission'),
                        'forwardToCommissionDisabledReason' => empty($forwardReasons) ? null : implode(' ', $forwardReasons),
                        'canForwardToDgp' => $isRH && $recourse->current_instance === 'Comissao' && in_array($recourse->status, ['respondido', 'indeferido']),
                        'canDgpDecide' => ($stage === 'dgp_review') && (user_can('recourses.dgpDecision') || $this->hasRole('Diretor RH') || $this->hasRole('DGP')),
                        'canDgpReturnToCommission' => ($stage === 'dgp_review') && (user_can('recourses.dgpReturnToCommission') || $this->hasRole('Diretor RH') || $this->hasRole('DGP')),
                        'canRhFinalizeFirst' => $isRH && $stage === 'rh_finalize_first' && !empty($recourse->dgp_decision),
                        'canForwardToSecretary' => $isRH && $stage === 'rh_forward_secretary' && (bool)$recourse->is_second_instance,
                        'canSecretaryDecide' => ($stage === 'secretary_review') && (user_can('recourses.secretaryDecision') || $this->hasRole('Secretario Gestão') || $this->hasRole('Secretário') || $this->hasRole('Secretaria')),
                        'canRhFinalizeSecond' => $isRH && $stage === 'rh_finalize_second' && !empty($recourse->secretary_decision),
                    ];
                })(),
                'attachments' => $recourse->attachments->map(fn($a) => [
                    'name' => $a->original_name,
                    'url' => Storage::url($a->file_path),
                ]),
                'responseAttachments' => $recourse->responseAttachments->map(fn($a) => [
                    'name' => $a->original_name,
                    'url' => Storage::url($a->file_path),
                ]),
                'responsiblePersons' => $recourse->responsiblePersons->map(fn($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'registration_number' => $p->registration_number,
                ]),
                'person' => [
                    'name' => $recourse->person->name,
                ],
                'evaluation' => [
                    'id' => $evaluationToShow->id,
                    'year' => optional($evaluationToShow->form)->year ?? '—',
                    'type' => $evaluationToShow->type ?? '—',
                    'form_name' => $evaluationToShow->form->name ?? '—',
                    'avaliado' => $evaluationToShow->evaluatedPerson->name ?? '—',
                    'answers' => $evaluationToShow->answers->map(fn($a) => [
                        'question' => $a->question->text ?? '',
                        'score' => $a->score,
                    ]),
                    'is_chef_evaluation' => $chefEvaluation !== null, // Indica se é realmente do chefe
                    'original_evaluation_type' => $recourse->evaluation->evaluation->type, // Tipo da avaliação original
                ],
                'logs' => $recourse->logs->map(fn($log) => [
                    'status' => $log->status,
                    'message' => $log->message,
                    'created_at' => $log->created_at->format('d/m/Y H:i'),
                ]),
                'last_return' => $recourse->lastReturnedBy ? [
                    'by' => $recourse->lastReturnedBy->name,
                    'to' => $recourse->last_returned_to_instance,
                    'at' => optional($recourse->last_returned_at)?->format('d/m/Y H:i'),
                ] : null,
            ],
            'availablePersons' => $availablePersons,
            // DGP/Secretário não podem gerenciar responsáveis
            'canManageAssignees' => $canManageAssignees && !(user_can('recourses.dgpDecision') || $this->hasRole('Diretor RH') || $this->hasRole('DGP') || user_can('recourses.secretaryDecision') || $this->hasRole('Secretario Gestão') || $this->hasRole('Secretário') || $this->hasRole('Secretaria')),
            'userRole' => $isComissao ? 'Comissão' : ((user_can('recourses.dgpDecision') || $this->hasRole('Diretor RH') || $this->hasRole('DGP')) ? 'DGP' : ((user_can('recourses.secretaryDecision') || $this->hasRole('Secretario Gestão') || $this->hasRole('Secretário') || $this->hasRole('Secretaria')) ? 'Secretário' : ($isRH ? 'RH' : 'Sem permissão'))),
            // Somente Comissão responsável pode decidir quando a instância atual é Comissão
            'canDecideNow' => (
                $recourse->current_instance === 'Comissao' && $this->isResponsibleForRecourse($recourse)
            ),
        ]);
    }

    /**
     * RH encaminha o recurso para a Comissão responsável iniciar a análise (etapa 1 -> 2)
     */
    public function forwardToCommission(Request $request, EvaluationRecourse $recourse)
    {
        // Permissão: apenas RH puro pode encaminhar para comissão
        if (!user_can('recourses.forwardToCommission')) {
            return redirect()->back()->with('error', 'Você não tem permissão para encaminhar recursos.');
        }

        $user = Auth::user();
        $isComissao = $user && $user->roles->pluck('name')->contains('Comissão');
        // Bloqueia DGP e Secretário de encaminhar
        $isDgp = user_can('recourses.dgpDecision') || $this->hasRole('Diretor RH') || $this->hasRole('DGP');
        $isSecretary = user_can('recourses.secretaryDecision') || $this->hasRole('Secretario Gestão') || $this->hasRole('Secretário') || $this->hasRole('Secretaria');
        if ($isComissao || $isDgp || $isSecretary) {
            return redirect()->back()->with('error', 'Apenas o RH pode encaminhar recursos para a Comissão.');
        }

        // Regras: só pode encaminhar quando está no RH e ainda não está com a Comissão
        if ($recourse->current_instance !== 'RH') {
            return redirect()->back()->with('error', 'Recurso não está na instância do RH.');
        }

        // Deve haver pelo menos um responsável atribuído
        if ($recourse->responsiblePersons()->count() === 0) {
            return redirect()->back()->with('error', 'Atribua ao menos um membro da Comissão antes de encaminhar.');
        }

        // Se o recurso foi devolvido anteriormente ao RH, exigir justificativa para o reenvio
        $needsJustification = !is_null($recourse->last_returned_at);
        $justification = null;
        if ($needsJustification) {
            $data = $request->validate([
                'message' => ['required', 'string', 'min:5'],
            ]);
            $justification = $data['message'];
        }

        $recourse->update([
            'current_instance' => 'Comissao',
            'workflow_stage' => 'commission_analysis',
        ]);

        $logMessage = 'RH encaminhou o recurso para a Comissão responsável iniciar a análise.';
        if ($justification) {
            $logMessage .= ' Justificativa do reenvio: ' . $justification;
        }
        $recourse->logs()->create([
            'status' => 'encaminhado_comissao',
            'message' => $logMessage,
        ]);

        return redirect()->route('recourses.review', $recourse->id)->with('success', 'Recurso encaminhado para a Comissão.');
    }

    /**
     * DGP devolve para a Comissão com justificativa
     */
    public function dgpReturnToCommission(Request $request, EvaluationRecourse $recourse)
    {
        if (!(user_can('recourses.dgpReturnToCommission') || user_can('recourses.dgpDecision') || $this->hasRole('Diretor RH') || $this->hasRole('DGP'))) {
            return redirect()->back()->with('error', 'Você não tem permissão para devolver para a Comissão.');
        }
        if ($this->getStage($recourse) !== 'dgp_review') {
            return redirect()->back()->with('error', 'O recurso não está na etapa da DGP.');
        }
        $data = $request->validate([
            'message' => ['required', 'string', 'min:5'],
        ]);

        $recourse->update([
            'current_instance' => 'Comissao',
            'workflow_stage' => 'commission_analysis',
            'status' => 'em_analise',
            'last_returned_by_user_id' => Auth::id(),
            'last_returned_to_instance' => 'Comissao',
            'last_returned_at' => now(),
        ]);

        $recourse->logs()->create([
            'status' => 'devolvido_dgp',
            'message' => 'DGP devolveu o recurso para a Comissão. Justificativa: ' . $data['message'],
        ]);

        return redirect()->route('recourses.review', $recourse->id)->with('success', 'Recurso devolvido para a Comissão.');
    }

    public function markAnalyzing(EvaluationRecourse $recourse)
    {
        if (!user_can('recourses.markAnalyzing')) {
            return redirect()->back()->with('error', 'Você não tem permissão para iniciar a análise.');
        }
        // Somente Comissão responsável pode iniciar análise
        if (!$this->isResponsibleForRecourse($recourse) || $recourse->current_instance !== 'Comissao') {
            return redirect()->back()->with('error', 'Apenas a Comissão responsável pode iniciar a análise.');
        }

        if ($recourse->status !== 'em_analise') {
            $recourse->update(['status' => 'em_analise']);

            $recourse->logs()->create([
                'status' => 'em_analise',
                'message' => 'Comissão iniciou a análise ao acessar um anexo',
            ]);
        }

        return redirect()
            ->back();
    }

    public function respond(Request $request, EvaluationRecourse $recourse)
    {
        if (!user_can('recourses.respond')) {
            return redirect()->back()->with('error', 'Você não tem permissão para responder recursos.');
        }
        // Somente Comissão responsável pode responder, e apenas quando a instância atual é Comissão
        if (!$this->isResponsibleForRecourse($recourse) || $recourse->current_instance !== 'Comissao') {
            return redirect()->back()->with('error', 'Apenas a Comissão responsável pode responder este recurso.');
        }

        $validated = $request->validate([
            'status' => ['required', 'in:respondido,indeferido'],
            'response' => ['required', 'string', 'min:5'],
            'response_attachments.*' => ['file', 'max:10240'], // Máximo 10MB por arquivo
        ]);

        $recourse->update([
            'status' => $validated['status'],
            'response' => $validated['response'],
            'responded_at' => now(),
        ]);

        $recourse->logs()->create([
            'status' => $validated['status'],
            'message' => 'Parecer da comissão registrado.',
        ]);

        // Salva anexos de resposta se houver
        if ($request->hasFile('response_attachments')) {
            foreach ($request->file('response_attachments') as $file) {
                $path = $file->store('recourse_responses', 'public');
                EvaluationRecourseResponseAttachment::create([
                    'recourse_id' => $recourse->id,
                    'file_path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                ]);
            }
        }

        // Encaminhamento automático para DGP (Diretoria/Diretor do RH) após parecer da Comissão
        $recourse->update([
            'current_instance' => 'RH',
            'workflow_stage' => 'dgp_review',
        ]);
        $recourse->logs()->create([
            'status' => 'encaminhado_dgp',
            'message' => 'Sistema encaminhou automaticamente à DGP após parecer da Comissão.',
        ]);

        return redirect()
            ->back()
            ->with('success', 'Parecer salvo com sucesso!');
    }

    /**
     * RH remete o processo à DGP após parecer da Comissão (etapa Comissão -> DGP)
     */
    public function forwardToDgp(EvaluationRecourse $recourse)
    {
        // Só RH puro pode encaminhar
        $user = Auth::user();
        $isComissao = $user && $user->roles->pluck('name')->contains('Comissão');
        if ($isComissao || !$this->isRH()) {
            return redirect()->back()->with('error', 'Apenas o RH pode encaminhar à DGP.');
        }

        if ($recourse->current_instance !== 'Comissao' || !in_array($recourse->status, ['respondido', 'indeferido'])) {
            return redirect()->back()->with('error', 'O recurso precisa estar com a Comissão e já ter parecer.');
        }

        $recourse->update([
            'current_instance' => 'RH',
            'workflow_stage' => 'dgp_review',
        ]);

        $recourse->logs()->create([
            'status' => 'encaminhado_dgp',
            'message' => 'RH remeteu o processo à DGP para análise/homologação.',
        ]);

        return redirect()->route('recourses.review', $recourse->id)->with('success', 'Encaminhado à DGP.');
    }

    /**
     * DGP analisa e homologa decisão da Subcomissão
     */
    public function dgpDecision(Request $request, EvaluationRecourse $recourse)
    {
        if (!(user_can('recourses.dgpDecision') || $this->hasRole('DGP') || $this->hasRole('Diretor RH'))) {
            return redirect()->back()->with('error', 'Você não tem permissão para registrar decisão da DGP.');
        }

        // Deve estar na etapa de DGP
        if ($this->getStage($recourse) !== 'dgp_review') {
            return redirect()->back()->with('error', 'O recurso não está na etapa da DGP.');
        }

        $validated = $request->validate([
            'decision' => ['required', 'in:homologado,nao_homologado'],
            'notes' => [
                function ($attribute, $value, $fail) use ($request) {
                    if ($request->get('decision') === 'nao_homologado') {
                        if (!is_string($value) || strlen(trim($value)) < 5) {
                            $fail('Justificativa obrigatória para indeferir (mínimo 5 caracteres).');
                        }
                    }
                }
            ],
        ]);

        $recourse->update([
            'dgp_decision' => $validated['decision'],
            'dgp_decided_at' => now(),
            'dgp_notes' => $validated['notes'] ?? null,
            'workflow_stage' => 'rh_finalize_first',
        ]);

        $recourse->logs()->create([
            'status' => 'dgp_decidiu',
            'message' => 'DGP registrou decisão: ' . $validated['decision'],
        ]);

        return redirect()->route('recourses.review', $recourse->id)->with('success', 'Decisão da DGP registrada.');
    }

    /**
     * RH realiza trâmites finais e comunica o servidor para ciência (1ª instância)
     */
    public function rhFinalizeFirst(EvaluationRecourse $recourse)
    {
        $user = Auth::user();
        $isComissao = $user && $user->roles->pluck('name')->contains('Comissão');
        if ($isComissao || !$this->isRH()) {
            return redirect()->back()->with('error', 'Apenas RH pode finalizar.');
        }

        if ($this->getStage($recourse) !== 'rh_finalize_first' || !$recourse->dgp_decision) {
            return redirect()->back()->with('error', 'O recurso não está pronto para finalização pelo RH.');
        }

        $recourse->update([
            'stage' => 'await_first_ack', // aguarda ciência do servidor
            'workflow_stage' => 'await_first_ack',
        ]);

        $recourse->logs()->create([
            'status' => 'rh_finalizou_primeira',
            'message' => 'RH finalizou a primeira instância e comunicou o servidor para ciência.',
        ]);

        return redirect()->route('recourses.review', $recourse->id)->with('success', 'Finalização registrada. Aguardando ciência do servidor.');
    }

    /**
     * Servidor registra ciência da decisão de 1ª instância
     */
    public function acknowledgeFirst(EvaluationRecourse $recourse)
    {
        // Apenas o próprio servidor do recurso pode assinar
        $user = Auth::user();
        $person = Person::where('cpf', $user->cpf)->first();
        if (!$person || $person->id !== $recourse->person_id) {
            return redirect()->back()->with('error', 'Somente o servidor autor do recurso pode registrar ciência.');
        }
        if ($this->getStage($recourse) !== 'await_first_ack' || $recourse->first_ack_at) {
            return redirect()->back()->with('error', 'Este recurso não está aguardando ciência de 1ª instância.');
        }

        $recourse->update([
            'first_ack_at' => now(),
            'workflow_stage' => 'first_ack_done',
        ]);

        $recourse->logs()->create([
            'status' => 'ciencia_primeira',
            'message' => 'Servidor registrou ciência da decisão (1ª instância).',
        ]);

        return redirect()->route('recourses.show', $recourse->id)->with('success', 'Ciência registrada.');
    }

    /**
     * Servidor interpõe recurso em 2ª instância
     */
    public function requestSecondInstance(Request $request, EvaluationRecourse $recourse)
    {
        $user = Auth::user();
        $person = Person::where('cpf', $user->cpf)->first();
        if (!$person || $person->id !== $recourse->person_id) {
            return redirect()->back()->with('error', 'Somente o servidor autor do recurso pode solicitar 2ª instância.');
        }

        if (!$recourse->first_ack_at || $recourse->is_second_instance) {
            return redirect()->back()->with('error', 'Fluxo inválido para 2ª instância.');
        }

        $validated = $request->validate([
            'text' => ['required', 'string', 'min:5'],
        ]);

        $recourse->update([
            'is_second_instance' => true,
            'second_instance_requested_at' => now(),
            'second_instance_text' => $validated['text'],
            'workflow_stage' => 'rh_forward_secretary',
            'current_instance' => 'RH',
        ]);

        $recourse->logs()->create([
            'status' => 'segunda_instancia_solicitada',
            'message' => 'Servidor solicitou recurso em 2ª instância.',
        ]);

        return redirect()->route('recourses.show', $recourse->id)->with('success', '2ª instância solicitada. RH será notificado.');
    }

    /**
     * RH encaminha o processo ao Secretário
     */
    public function forwardToSecretary(EvaluationRecourse $recourse)
    {
        $user = Auth::user();
        $isComissao = $user && $user->roles->pluck('name')->contains('Comissão');
        if ($isComissao || !$this->isRH()) {
            return redirect()->back()->with('error', 'Apenas RH pode encaminhar ao Secretário.');
        }

        if ($this->getStage($recourse) !== 'rh_forward_secretary' || !$recourse->is_second_instance) {
            return redirect()->back()->with('error', 'Este recurso não está pronto para ser encaminhado ao Secretário.');
        }

        $recourse->update([
            'current_instance' => 'Comissao', // reutilizando campo; instância externa
            'workflow_stage' => 'secretary_review',
        ]);

        $recourse->logs()->create([
            'status' => 'encaminhado_secretario',
            'message' => 'RH encaminhou o processo ao Secretário para análise/homologação.',
        ]);

        return redirect()->route('recourses.review', $recourse->id)->with('success', 'Encaminhado ao Secretário.');
    }

    /**
     * Secretário analisa e homologa a decisão de 1ª instância
     */
    public function secretaryDecision(Request $request, EvaluationRecourse $recourse)
    {
        if (!(user_can('recourses.secretaryDecision') || $this->hasRole('Secretário') || $this->hasRole('Secretaria') || $this->hasRole('Secretario Gestão'))) {
            return redirect()->back()->with('error', 'Você não tem permissão para registrar decisão do Secretário.');
        }

        if ($this->getStage($recourse) !== 'secretary_review' || !$recourse->is_second_instance) {
            return redirect()->back()->with('error', 'O recurso não está na etapa do Secretário.');
        }

        $validated = $request->validate([
            'decision' => ['required', 'in:homologado,nao_homologado'],
            'notes' => ['nullable', 'string'],
        ]);

        $recourse->update([
            'secretary_decision' => $validated['decision'],
            'secretary_decided_at' => now(),
            'secretary_notes' => $validated['notes'] ?? null,
            'workflow_stage' => 'rh_finalize_second',
            'current_instance' => 'RH',
        ]);

        $recourse->logs()->create([
            'status' => 'secretario_decidiu',
            'message' => 'Secretário registrou decisão: ' . $validated['decision'],
        ]);

        return redirect()->route('recourses.review', $recourse->id)->with('success', 'Decisão do Secretário registrada.');
    }

    /**
     * RH realiza trâmites finais da 2ª instância e comunica o servidor
     */
    public function rhFinalizeSecond(EvaluationRecourse $recourse)
    {
        $user = Auth::user();
        $isComissao = $user && $user->roles->pluck('name')->contains('Comissão');
        if ($isComissao || !$this->isRH()) {
            return redirect()->back()->with('error', 'Apenas RH pode finalizar.');
        }

        if ($this->getStage($recourse) !== 'rh_finalize_second' || !$recourse->secretary_decision) {
            return redirect()->back()->with('error', 'O recurso não está pronto para finalização (2ª instância).');
        }

        $recourse->update([
            'workflow_stage' => 'await_second_ack',
        ]);

        $recourse->logs()->create([
            'status' => 'rh_finalizou_segunda',
            'message' => 'RH finalizou a 2ª instância e comunicou o servidor para ciência.',
        ]);

        return redirect()->route('recourses.review', $recourse->id)->with('success', 'Finalização registrada. Aguardando ciência do servidor.');
    }

    /**
     * Servidor registra ciência da decisão de 2ª instância
     */
    public function acknowledgeSecond(EvaluationRecourse $recourse)
    {
        $user = Auth::user();
        $person = Person::where('cpf', $user->cpf)->first();
        if (!$person || $person->id !== $recourse->person_id) {
            return redirect()->back()->with('error', 'Somente o servidor autor do recurso pode registrar ciência.');
        }
        if ($this->getStage($recourse) !== 'await_second_ack' || $recourse->second_ack_at) {
            return redirect()->back()->with('error', 'Este recurso não está aguardando ciência de 2ª instância.');
        }

        $recourse->update([
            'second_ack_at' => now(),
            'workflow_stage' => 'completed',
        ]);

        $recourse->logs()->create([
            'status' => 'ciencia_segunda',
            'message' => 'Servidor registrou ciência da decisão (2ª instância).',
        ]);

        return redirect()->route('recourses.show', $recourse->id)->with('success', 'Ciência registrada. Processo concluído.');
    }

    /**
     * Devolver o processo para a instância anterior, registrando quem devolveu e para quem foi devolvido.
     * Regra: RH pode devolver para Comissão quando estiver com RH; Comissão pode devolver para RH quando estiver com Comissão.
     */
    public function returnToPreviousInstance(Request $request, EvaluationRecourse $recourse)
    {
        if (!user_can('recourses.return')) {
            return redirect()->back()->with('error', 'Você não tem permissão para devolver recursos.');
        }
        // Apenas Comissão responsável pode devolver para o RH, quando a instância atual é Comissão
        if (!$this->isResponsibleForRecourse($recourse) || $recourse->current_instance !== 'Comissao') {
            return redirect()->back()->with('error', 'Apenas a Comissão responsável pode devolver este recurso ao RH.');
        }

        $validated = $request->validate([
            'message' => ['required', 'string', 'min:5'],
        ]);

        $user = Auth::user();
        // Determinar para qual instância será devolvido: sempre para RH neste fluxo
        $to = 'RH';

        // Atualiza instância e registra devolução
        $recourse->update([
            'current_instance' => $to,
            'workflow_stage' => 'rh_analysis',
            'last_returned_by_user_id' => $user->id,
            'last_returned_to_instance' => $to,
            'last_returned_at' => now(),
            // Mantemos o status dentro do enum permitido
            // Se estava respondido/indeferido e foi devolvido para ajustes, marcamos como em_analise
            'status' => 'em_analise',
        ]);

        $byName = $user->name;
        $recourse->logs()->create([
            'status' => 'devolvido',
            'message' => "Recurso devolvido para {$to} por {$byName}. Justificativa: " . $validated['message'],
        ]);

        return redirect()->route('recourses.index')->with('success', 'Recurso devolvido com sucesso.');
    }

    public function assignResponsible(Request $request, EvaluationRecourse $recourse)
    {
        $user = Auth::user();
        $isRH = $this->isRH();
        $isComissao = $user && $user->roles->pluck('name')->contains('Comissão');
        $isDgp = user_can('recourses.dgpDecision') || $this->hasRole('Diretor RH') || $this->hasRole('DGP');
        $isSecretary = user_can('recourses.secretaryDecision') || $this->hasRole('Secretario Gestão') || $this->hasRole('Secretário') || $this->hasRole('Secretaria');
        
        // Apenas RH puro (que não é Comissão/DGP/Secretário) pode atribuir responsáveis
        if (!$isRH || $isComissao || $isDgp || $isSecretary) {
            return redirect()->back()->with('error', 'Apenas o RH pode atribuir responsáveis.');
        }

        $validated = $request->validate([
            'person_id' => ['required', 'exists:people,id'],
        ]);

        // Verifica se a pessoa tem role "Comissão"
        $person = Person::find($validated['person_id']);
        if (!$person) {
            return redirect()->back()->with('error', 'Pessoa não encontrada.');
        }

        // Verifica se a pessoa tem role "Comissão" através do CPF
        $user = User::where('cpf', $person->cpf)->first();
        if (!$user || !$user->roles->pluck('name')->contains('Comissão')) {
            return redirect()->back()->with('error', 'Apenas pessoas com role "Comissão" podem ser responsáveis por recursos.');
        }

        $currentUser = Auth::user();
        $assignedBy = Person::where('cpf', $currentUser->cpf)->first();

        // Verifica se a pessoa já é responsável
        $exists = EvaluationRecourseAssignee::where('recourse_id', $recourse->id)
                                          ->where('person_id', $validated['person_id'])
                                          ->exists();

        if ($exists) {
            return redirect()->back()->with('error', 'Esta pessoa já é responsável por este recurso.');
        }

        EvaluationRecourseAssignee::create([
            'recourse_id' => $recourse->id,
            'person_id' => $validated['person_id'],
            'assigned_by' => $assignedBy?->id,
            'assigned_at' => now(),
        ]);

        $assignedPerson = Person::find($validated['person_id']);
        
        $recourse->logs()->create([
            'status' => 'responsavel_atribuido',
            'message' => "Responsável atribuído: {$assignedPerson->name}",
        ]);

        return redirect()->back()->with('success', 'Responsável atribuído com sucesso!');
    }

    public function removeResponsible(Request $request, EvaluationRecourse $recourse)
    {
        $user = Auth::user();
        $isRH = $this->isRH();
        $isComissao = $user && $user->roles->pluck('name')->contains('Comissão');
        $isDgp = user_can('recourses.dgpDecision') || $this->hasRole('Diretor RH') || $this->hasRole('DGP');
        $isSecretary = user_can('recourses.secretaryDecision') || $this->hasRole('Secretario Gestão') || $this->hasRole('Secretário') || $this->hasRole('Secretaria');
        
        // Apenas RH puro (que não é Comissão/DGP/Secretário) pode remover responsáveis
        if (!$isRH || $isComissao || $isDgp || $isSecretary) {
            return redirect()->back()->with('error', 'Apenas o RH pode remover responsáveis.');
        }

        $validated = $request->validate([
            'person_id' => ['required', 'exists:people,id'],
        ]);

        $assignee = EvaluationRecourseAssignee::where('recourse_id', $recourse->id)
                                             ->where('person_id', $validated['person_id'])
                                             ->first();

        if (!$assignee) {
            return redirect()->back()->with('error', 'Esta pessoa não é responsável por este recurso.');
        }

        $removedPerson = Person::find($validated['person_id']);
        $assignee->delete();

        $recourse->logs()->create([
            'status' => 'responsavel_removido',
            'message' => "Responsável removido: {$removedPerson->name}",
        ]);

        return redirect()->back()->with('success', 'Responsável removido com sucesso!');
    }
}
