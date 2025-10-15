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
        if ($recourse->current_instance === 'Comissao' && in_array($stage, ['commission_analysis','commission_clarification'], true)) {
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

        // Lista principal (mantida para RH e contexto geral)
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
                    'stage' => $this->getStage($recourse),
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

        // Conjunto "Aguardando minha decisão" de acordo com o papel
        $awaiting = collect();
        if ($isComissao && $person) {
            $awaiting = EvaluationRecourse::with(['person'])
                ->whereHas('responsiblePersons', function($q) use ($person) {
                    $q->where('person_id', $person->id);
                })
                ->where('current_instance', 'Comissao')
                ->where(function($q){
                    $q->where('workflow_stage', 'commission_analysis')
                      ->orWhere(function($q2){ $q2->whereNull('workflow_stage')->where('stage', 'commission_analysis'); });
                })
                ->whereNotIn('status', ['respondido', 'indeferido'])
                ->latest()
                ->take(10)
                ->get()
                ->map(function($recourse){
                    return [
                        'id' => $recourse->id,
                        'status' => $recourse->status,
                        'text' => $recourse->text,
                        'person' => [ 'name' => $recourse->person->name ?? '—' ],
                        'evaluation' => [ 'id' => $recourse->evaluation->id ?? null, 'year' => '—' ],
                    ];
                });
        } elseif ($isDgp) {
            $awaiting = EvaluationRecourse::with(['person'])
                ->where(function($q){
                    $q->where('workflow_stage', 'dgp_review')
                      ->orWhere(function($q2){ $q2->whereNull('workflow_stage')->where('stage', 'dgp_review'); });
                })
                ->latest()
                ->take(10)
                ->get()
                ->map(function($recourse){
                    return [
                        'id' => $recourse->id,
                        'status' => $recourse->status,
                        'text' => $recourse->text,
                        'person' => [ 'name' => $recourse->person->name ?? '—' ],
                        'evaluation' => [ 'id' => $recourse->evaluation->id ?? null, 'year' => '—' ],
                    ];
                });
        } elseif ($isSecretary) {
            $awaiting = EvaluationRecourse::with(['person'])
                ->where(function($q){
                    $q->where('workflow_stage', 'secretary_review')
                      ->orWhere(function($q2){ $q2->whereNull('workflow_stage')->where('stage', 'secretary_review'); });
                })
                ->latest()
                ->take(10)
                ->get()
                ->map(function($recourse){
                    return [
                        'id' => $recourse->id,
                        'status' => $recourse->status,
                        'text' => $recourse->text,
                        'person' => [ 'name' => $recourse->person->name ?? '—' ],
                        'evaluation' => [ 'id' => $recourse->evaluation->id ?? null, 'year' => '—' ],
                    ];
                });
        }

        return inertia('Recourses/Index', [
            'recourses' => $recourses,
            'awaiting' => $awaiting,
            'status' => $status ?? 'todos', // Para mostrar no título
            'canManageAssignees' => $isRH && !$isComissao, // Apenas RH puro pode gerenciar responsáveis
            'userRole' => $isComissao ? 'Comissão' : ($isSecretary ? 'Secretário' : ($isDgp ? 'DGP' : ($isRH ? 'RH' : 'Sem permissão'))),
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
            // Increased max single attachment size from 10MB to 100MB (102400 KB)
            'attachments.*' => 'file|max:102400',
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
        $user = Auth::user();
        $person = Person::where('cpf', $user->cpf)->first();
        $isRH = user_can('recourse');
        $isComissao = $user && $user->roles->pluck('name')->contains('Comissão');
        $isRequerente = $person && $recourse->person_id === $person->id;
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
                // homologado/homologar => tratar como 'respondido' (deferido) para anular chefe; nao_homologado/nao_homologar => 'indeferido'
                $statusForScore = $recourse->status; // padrão: decisão da comissão
                    if (!empty($recourse->secretary_decision)) {
                        $approved = ['homologado','homologar','deferido','deferir','aprovado','aprovar','sim'];
                        $statusForScore = in_array($recourse->secretary_decision, $approved, true) ? 'respondido' : 'indeferido';
                } elseif (!empty($recourse->dgp_decision)) {
                        $approved = ['homologado','homologar','deferido','deferir','aprovado','aprovar','sim'];
                        $statusForScore = in_array($recourse->dgp_decision, $approved, true) ? 'respondido' : 'indeferido';
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
                // Comissão: decisão estruturada (mantemos status global mascarado em 'em_analise')
                'commission' => [
                    'decision' => $recourse->commission_decision, // 'deferido' | 'indeferido' | null
                    'response' => $recourse->commission_response,
                    'decided_at' => optional($recourse->commission_decided_at)?->format('Y-m-d H:i'),
                    'clarification' => [
                        'response' => $recourse->clarification_response,
                        'responded_at' => optional($recourse->clarification_responded_at)?->format('Y-m-d H:i'),
                        'attachments' => $recourse->attachments
                            ->where('context','clarification_response')
                            ->map(fn($a)=>['name'=>$a->original_name,'url'=>Storage::url($a->file_path)])->values(),
                    ],
                ],
                'responded_at' => optional($recourse->responded_at)?->format('Y-m-d'),
                // Extended workflow fields
                'dgp' => [
                    'decision' => $recourse->dgp_decision,
                    'decided_at' => optional($recourse->dgp_decided_at)?->format('Y-m-d H:i'),
                    'notes' => $recourse->dgp_notes,
                ],
                'secretary' => [
                    'decision' => $recourse->secretary_decision,
                    'decided_at' => optional($recourse->secretary_decided_at)?->format('Y-m-d H:i'),
                    'notes' => $recourse->secretary_notes,
                ],
                // Nota final após decisão (DGP ou Secretário)
                'final_score' => ($recourse->dgp_decision || $recourse->secretary_decision) ? $finalScore : null,
                'first_ack_at' => optional($recourse->first_ack_at)?->format('Y-m-d H:i'),
                'first_ack_signature_base64' => $recourse->first_ack_signature_base64,
                'second_instance' => [
                    'enabled' => (bool) $recourse->is_second_instance,
                    'requested_at' => optional($recourse->second_instance_requested_at)?->format('Y-m-d H:i'),
                    'text' => $recourse->second_instance_text,
                ],
                'second_ack_at' => optional($recourse->second_ack_at)?->format('Y-m-d H:i'),
                'second_ack_signature_base64' => $recourse->second_ack_signature_base64,
                'last_return' => $recourse->lastReturnedBy ? [
                    'by' => $recourse->lastReturnedBy->name,
                    'to' => $recourse->last_returned_to_instance,
                    'at' => optional($recourse->last_returned_at)?->format('d/m/Y H:i'),
                    'message' => $recourse->last_return_message,
                    'attachments' => $recourse->attachments
                        ->where('context', 'dgp_return')
                        ->map(fn($a) => [ 'name' => $a->original_name, 'url' => Storage::url($a->file_path) ])->values(),
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

        $user = Auth::user();
        $person = Person::where('cpf', $user->cpf)->first();
        $isRH = $this->isRH();
        $isComissao = $user && $user->roles->pluck('name')->contains('Comissão');
        $isRequerente = $person && $recourse->person_id === $person->id;

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
    // Apenas RH puro pode gerenciar responsáveis e somente enquanto o recurso estiver na instância do RH
    $canManageAssignees = $isRH && !$isComissao && $recourse->current_instance === 'RH';
        
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
                // Comissão: decisão estruturada
                'commission' => [
                    'decision' => $recourse->commission_decision,
                    'response' => $recourse->commission_response,
                    'decided_at' => optional($recourse->commission_decided_at)?->format('Y-m-d H:i'),
                    'clarification' => [
                        'response' => $recourse->clarification_response,
                        'responded_at' => optional($recourse->clarification_responded_at)?->format('Y-m-d H:i'),
                        'attachments' => $recourse->attachments
                            ->where('context','clarification_response')
                            ->map(fn($a)=>['name'=>$a->original_name,'url'=>Storage::url($a->file_path)])->values(),
                    ],
                ],
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
                    if (($recourse->responsiblePersons?->count() ?? 0) === 0) $forwardReasons[] = 'Defina o Presidente da Comissão antes de encaminhar.';
                    if (!user_can('recourses.forwardToCommission')) $forwardReasons[] = 'Usuário sem permissão para encaminhar.';
                    $stage = $this->getStage($recourse);
                    return [
                        'canForwardToCommission' => $isRH && $recourse->current_instance === 'RH' && ($recourse->responsiblePersons?->count() ?? 0) > 0 && user_can('recourses.forwardToCommission'),
                        'forwardToCommissionDisabledReason' => empty($forwardReasons) ? null : implode(' ', $forwardReasons),
                        'canForwardToDgp' => $isRH && $recourse->current_instance === 'Comissao' && in_array($recourse->status, ['respondido', 'indeferido']),
                        'canDgpDecide' => ($stage === 'dgp_review') && (user_can('recourses.dgpDecision') || $this->hasRole('Diretor RH') || $this->hasRole('DGP')),
                        'canDgpReturnToCommission' => ($stage === 'dgp_review') && (user_can('recourses.dgpReturnToCommission') || $this->hasRole('Diretor RH') || $this->hasRole('DGP')),
                        'canRhFinalizeFirst' => false,
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
                    'message' => $recourse->last_return_message,
                    'attachments' => $recourse->attachments
                        ->where('context','dgp_return')
                        ->map(fn($a)=>['name'=>$a->original_name,'url'=>Storage::url($a->file_path)])->values(),
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

        // Deve haver um Presidente definido
        if ($recourse->responsiblePersons()->count() === 0) {
            return redirect()->back()->with('error', 'Defina o Presidente da Comissão antes de encaminhar.');
        }

        // Sempre permitir anexar documentos e opcionalmente registrar mensagem.
        $wasReturnedBefore = !is_null($recourse->last_returned_at);
        $data = $request->validate([
            // Mensagem obrigatória somente se foi devolvido anteriormente
            'message' => [$wasReturnedBefore ? 'required' : 'nullable', 'string', $wasReturnedBefore ? 'min:5' : 'nullable'],
            // RH -> Comissão (reenvio) attachments up to 100MB
            'forward_attachments.*' => ['file', 'max:102400'],
        ]);
        $justification = $data['message'] ?? null;

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

        // Armazenar anexos (sempre que enviados)
        if ($request->hasFile('forward_attachments')) {
            foreach ($request->file('forward_attachments') as $file) {
                $path = $file->store('recourse_transitions', 'public');
                \App\Models\EvaluationRecourseAttachment::create([
                    'recourse_id' => $recourse->id,
                    'file_path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'context' => 'forward',
                ]);
            }
        }

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
            'return_attachments.*' => ['file', 'max:102400'],
        ]);

        $recourse->update([
            'current_instance' => 'Comissao',
            'workflow_stage' => 'commission_clarification', // nova etapa específica de esclarecimento
            'status' => 'em_analise', // permanece mascarado
            'last_returned_by_user_id' => Auth::id(),
            'last_returned_to_instance' => 'Comissao',
            'last_returned_at' => now(),
            'last_return_message' => $data['message'],
        ]);

        $recourse->logs()->create([
            'status' => 'devolvido_dgp',
            'message' => 'DGP solicitou esclarecimentos adicionais à Comissão. Justificativa: ' . $data['message'],
        ]);

        // Armazenar anexos da devolução (se houver)
        if ($request->hasFile('return_attachments')) {
            foreach ($request->file('return_attachments') as $file) {
                $path = $file->store('recourse_transitions', 'public');
                \App\Models\EvaluationRecourseAttachment::create([
                    'recourse_id' => $recourse->id,
                    'file_path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'context' => 'dgp_return',
                ]);
            }
        }

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

        // Bloqueia reedição do parecer original: se já existe decisão da comissão e não estamos em etapa de esclarecimento
        $stage = $this->getStage($recourse);
        if (!empty($recourse->commission_decision) && $stage !== 'commission_clarification') {
            return redirect()->back()->with('error', 'Parecer original já registrado. Utilize o campo de esclarecimento (se disponível).');
        }
        // Também impede que a etapa de esclarecimento use este endpoint (deve usar respondClarification)
        if ($stage === 'commission_clarification') {
            return redirect()->back()->with('error', 'Esta etapa aceita apenas resposta de esclarecimento, não um novo parecer completo.');
        }

        $validated = $request->validate([
            // Mantemos a decisão da comissão apenas para fins de log; status do recurso permanece 'em_analise'
            'status' => ['required', 'in:respondido,indeferido'],
            'response' => ['required', 'string', 'min:5'],
            'response_attachments' => [function($attribute,$value,$fail) use ($request){
                if (!$request->hasFile('response_attachments')) {
                    $fail('Envie pelo menos um documento de apoio ao parecer.');
                }
            }],
            'response_attachments.*' => ['file', 'max:102400'], // Máximo 100MB por arquivo
        ]);

        // Não alterar o status global aqui para evitar que apareça como "deferido"; manter em análise até a DGP
        $updates = [
            'response' => $validated['response'],
            'responded_at' => now(),
            // Persist structured commission decision fields (new workflow columns)
            'commission_decision' => $validated['status'] === 'respondido' ? 'deferido' : 'indeferido',
            'commission_response' => $validated['response'],
            'commission_decided_at' => now(),
        ];
        if ($recourse->status !== 'em_analise') {
            $updates['status'] = 'em_analise';
        }
        $recourse->update($updates);

        // Log genérico sem expor deferimento/indeferimento ao histórico inicial
        $recourse->logs()->create([
            'status' => 'analise_concluida',
            'message' => 'Comissão concluiu a análise e encaminhou para homologação da DGP.',
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
        // Mantido apenas o log genérico acima; evita duplicação de informação

        return redirect()
            ->back()
            ->with('success', 'Parecer salvo com sucesso!');
    }

    /**
     * Comissão responde solicitação de esclarecimento da DGP sem alterar a decisão original
     */
    public function respondClarification(Request $request, EvaluationRecourse $recourse)
    {
        if (!user_can('recourses.respond')) {
            return redirect()->back()->with('error', 'Você não tem permissão para responder esclarecimentos.');
        }
        if (!$this->isResponsibleForRecourse($recourse) || $recourse->current_instance !== 'Comissao') {
            return redirect()->back()->with('error', 'Apenas a Comissão responsável pode responder.');
        }
        $stage = $this->getStage($recourse);
        if ($stage !== 'commission_clarification') {
            return redirect()->back()->with('error', 'Este recurso não está na etapa de esclarecimento.');
        }
        if (empty($recourse->commission_decision)) {
            return redirect()->back()->with('error', 'Não é possível responder esclarecimento antes do parecer original.');
        }

        $data = $request->validate([
            'clarification_response' => ['required','string','min:3'],
            'clarification_attachments.*' => ['file','max:102400'],
        ]);

        $recourse->update([
            'clarification_response' => $data['clarification_response'],
            'clarification_responded_at' => now(),
            // Após esclarecimento volta para análise/homologação da DGP
            'workflow_stage' => 'dgp_review',
            'current_instance' => 'RH',
        ]);

        $recourse->logs()->create([
            'status' => 'clarificacao_respondida',
            'message' => 'Comissão respondeu solicitação de esclarecimento da DGP.',
        ]);

        if ($request->hasFile('clarification_attachments')) {
            foreach ($request->file('clarification_attachments') as $file) {
                $path = $file->store('recourse_transitions', 'public');
                EvaluationRecourseAttachment::create([
                    'recourse_id' => $recourse->id,
                    'file_path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'context' => 'clarification_response',
                ]);
            }
        }

        return redirect()->route('recourses.review', $recourse->id)->with('success', 'Esclarecimento enviado à DGP.');
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
            'dgp_decision_attachments.*' => ['file', 'max:102400'],
        ]);

        $recourse->update([
            'dgp_decision' => $this->adaptDecisionForDatabase('dgp_decision', $validated['decision']),
            'dgp_decided_at' => now(),
            'dgp_notes' => $validated['notes'] ?? null,
            // Após decisão da DGP, pular a etapa manual de "Finalizar RH (1ª)" e ir direto para ciência do servidor
            'workflow_stage' => 'await_first_ack',
        ]);

        $recourse->logs()->create([
            'status' => 'dgp_decidiu',
            'message' => 'DGP registrou decisão: ' . $validated['decision'],
        ]);

        // Armazenar anexos da decisão da DGP (se houver)
        if ($request->hasFile('dgp_decision_attachments')) {
            foreach ($request->file('dgp_decision_attachments') as $file) {
                $path = $file->store('recourse_transitions', 'public');
                \App\Models\EvaluationRecourseAttachment::create([
                    'recourse_id' => $recourse->id,
                    'file_path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                ]);
            }
        }

        // Log adicional informando encaminhamento automático para ciência do servidor
        $recourse->logs()->create([
            'status' => 'rh_finalizou_primeira',
            'message' => 'Sistema encaminhou automaticamente para ciência do servidor (1ª instância) após decisão da DGP.',
        ]);

        return redirect()->route('recourses.review', $recourse->id)->with('success', 'Decisão da DGP registrada.');
    }

    // Etapa RH finalizar primeira instância foi automatizada após decisão da DGP.

    /**
     * Servidor registra ciência da decisão de 1ª instância
     */
    public function acknowledgeFirst(Request $request, EvaluationRecourse $recourse)
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

        $data = $request->validate([
            'signature_base64' => ['required','string','min:50'], // data URL
        ]);

        $recourse->update([
            'first_ack_at' => now(),
            'first_ack_signature_base64' => $data['signature_base64'],
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
            'second_instance_attachments.*' => ['file', 'max:102400'],
        ]);

        $recourse->update([
            'is_second_instance' => true,
            'second_instance_requested_at' => now(),
            'second_instance_text' => $validated['text'],
            // Encaminhamento automático ao Secretário após solicitação do servidor
            'workflow_stage' => 'secretary_review',
            'current_instance' => 'Comissao', // instância externa reaproveitada
        ]);

        // Registra que a 2ª instância foi solicitada e encaminhada automaticamente
        $recourse->logs()->create([
            'status' => 'segunda_instancia_solicitada',
            'message' => 'Servidor solicitou recurso em 2ª instância.',
        ]);
        $recourse->logs()->create([
            'status' => 'encaminhado_secretario',
            'message' => 'Sistema encaminhou automaticamente ao Secretário para análise/homologação (2ª instância).',
        ]);

        // Anexos enviados com o questionamento ao Secretário (se houver)
        if ($request->hasFile('second_instance_attachments')) {
            foreach ($request->file('second_instance_attachments') as $file) {
                $path = $file->store('recourse_transitions', 'public');
                \App\Models\EvaluationRecourseAttachment::create([
                    'recourse_id' => $recourse->id,
                    'file_path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                ]);
            }
        }

    return redirect()->route('recourses.show', $recourse->id)->with('success', '2ª instância solicitada e encaminhada ao Secretário.');
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
            'decision' => ['required', 'in:homologado,nao_homologado,homologar,nao_homologar'],
            'notes' => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) use ($request) {
                    $d = $request->get('decision');
                    if ($d === 'nao_homologado' || $d === 'nao_homologar') {
                        if (!is_string($value) || strlen(trim($value)) < 5) {
                            $fail('Justificativa obrigatória para não homologar (mínimo 5 caracteres).');
                        }
                    }
                }
            ],
            'secretary_decision_attachments.*' => ['file', 'max:102400'],
        ]);

        // Normaliza para valores canônicos e adapta ao tipo de coluna no banco (ENUM em MySQL pode ter variantes)
        $inputDecision = $validated['decision'];
        $canonicalDecision = in_array($inputDecision, ['homologar', 'homologado'])
            ? 'homologado'
            : (in_array($inputDecision, ['nao_homologar', 'nao_homologado']) ? 'nao_homologado' : $inputDecision);
        $normalizedDecision = $this->adaptDecisionForDatabase('secretary_decision', $canonicalDecision);

        // Em ambiente SQLite, contornar CHECK antigo com PRAGMA durante a escrita
        $usingSqlite = DB::connection()->getDriverName() === 'sqlite';
        if ($usingSqlite) {
            try { DB::statement('PRAGMA ignore_check_constraints = ON'); } catch (\Throwable $e) { /* ignore */ }
        }
        try {
            $recourse->update([
                'secretary_decision' => $normalizedDecision,
                'secretary_decided_at' => now(),
                'secretary_notes' => $validated['notes'] ?? null,
                'workflow_stage' => 'rh_finalize_second',
                'current_instance' => 'RH',
            ]);
        } finally {
            if ($usingSqlite) {
                try { DB::statement('PRAGMA ignore_check_constraints = OFF'); } catch (\Throwable $e) { /* ignore */ }
            }
        }

        $recourse->logs()->create([
            'status' => 'secretario_decidiu',
            'message' => 'Secretário registrou decisão: ' . $normalizedDecision,
        ]);

        // Armazenar anexos da decisão do Secretário (se houver)
        if ($request->hasFile('secretary_decision_attachments')) {
            foreach ($request->file('secretary_decision_attachments') as $file) {
                $path = $file->store('recourse_transitions', 'public');
                \App\Models\EvaluationRecourseAttachment::create([
                    'recourse_id' => $recourse->id,
                    'file_path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                ]);
            }
        }

        return redirect()->route('recourses.review', $recourse->id)->with('success', 'Decisão do Secretário registrada.');
    }

    /**
     * Ajusta a decisão ('homologado'/'nao_homologado') para o valor aceito pela coluna no banco,
     * especialmente quando a coluna é ENUM em MySQL com valores como 'homologar'/'nao_homologar' ou 'deferido'/'indeferido'.
     */
    private function adaptDecisionForDatabase(string $column, string $canonical): string
    {
        // Apenas tenta adaptar para MySQL; outros drivers guardam o canônico.
        try {
            if (DB::getDriverName() !== 'mysql') {
                return $canonical;
            }
            $row = collect(DB::select("SHOW COLUMNS FROM `evaluation_recourses` WHERE Field = ?", [$column]))->first();
            $colType = $row->Type ?? null; // MySQL returns 'Type' like enum('a','b')
            if (!$colType || stripos($colType, 'enum(') === false) {
                return $canonical;
            }
            if (!preg_match_all("/'([^']+)'/", $colType, $m)) {
                return $canonical;
            }
            $allowedOriginal = $m[1] ?? [];
            if (empty($allowedOriginal)) {
                return $canonical;
            }
            // Build lowercase map to preserve original tokens when returning
            $allowedLowerMap = [];
            foreach ($allowedOriginal as $tok) {
                $allowedLowerMap[mb_strtolower($tok)] = $tok;
            }
            $isApprove = ($canonical === 'homologado');
            $preferredApprove = ['homologado','homologar','deferido','deferir','aprovado','aprovar','sim'];
            $preferredDeny    = ['nao_homologado','nao_homologar','indeferido','indeferir','rejeitado','reprovar','nao'];
            $prefs = $isApprove ? $preferredApprove : $preferredDeny;
            foreach ($prefs as $opt) {
                $key = mb_strtolower($opt);
                if (array_key_exists($key, $allowedLowerMap)) {
                    return $allowedLowerMap[$key]; // Return with original case/diacritics as defined in enum
                }
            }
            return $canonical;
        } catch (\Throwable $e) {
            return $canonical;
        }
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
    public function acknowledgeSecond(Request $request, EvaluationRecourse $recourse)
    {
        $user = Auth::user();
        $person = Person::where('cpf', $user->cpf)->first();
        if (!$person || $person->id !== $recourse->person_id) {
            return redirect()->back()->with('error', 'Somente o servidor autor do recurso pode registrar ciência.');
        }
        if ($this->getStage($recourse) !== 'await_second_ack' || $recourse->second_ack_at) {
            return redirect()->back()->with('error', 'Este recurso não está aguardando ciência de 2ª instância.');
        }

        $data = $request->validate([
            'signature_base64' => ['required','string','min:50'],
        ]);

        $recourse->update([
            'second_ack_at' => now(),
            'second_ack_signature_base64' => $data['signature_base64'],
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
            'return_attachments.*' => ['file', 'max:102400'],
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

        // Armazenar anexos da devolução (se houver)
        if ($request->hasFile('return_attachments')) {
            foreach ($request->file('return_attachments') as $file) {
                $path = $file->store('recourse_transitions', 'public');
                \App\Models\EvaluationRecourseAttachment::create([
                    'recourse_id' => $recourse->id,
                    'file_path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                ]);
            }
        }

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

        // Nova regra: só pode existir UM presidente (responsável). Se já existir, substitui.
        $currentAssignee = EvaluationRecourseAssignee::where('recourse_id', $recourse->id)->first();
        if ($currentAssignee && $currentAssignee->person_id == $validated['person_id']) {
            return redirect()->back()->with('info', 'Esta pessoa já é o Presidente da Comissão para este recurso.');
        }

        DB::transaction(function () use ($recourse, $validated, $assignedBy, $currentAssignee) {
            if ($currentAssignee) {
                $oldPerson = $currentAssignee->person; // eager relation for log
                $currentAssignee->delete();
                $recourse->logs()->create([
                    'status' => 'presidente_removido',
                    'message' => 'Presidente anterior removido: ' . ($oldPerson?->name ?? '—'),
                ]);
            }

            EvaluationRecourseAssignee::create([
                'recourse_id' => $recourse->id,
                'person_id' => $validated['person_id'],
                'assigned_by' => $assignedBy?->id,
                'assigned_at' => now(),
            ]);

            $newPerson = Person::find($validated['person_id']);
            $recourse->logs()->create([
                'status' => 'presidente_atribuido',
                'message' => 'Presidente da Comissão definido: ' . ($newPerson?->name ?? '—'),
            ]);
        });

        return redirect()->back()->with('success', 'Presidente da Comissão definido com sucesso!');
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
            'status' => 'presidente_removido',
            'message' => "Presidente removido: {$removedPerson->name}",
        ]);

        return redirect()->back()->with('success', 'Presidente removido com sucesso!');
    }
}
