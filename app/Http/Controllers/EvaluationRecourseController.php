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
        return $this->isRH() || $this->isResponsibleForRecourse($recourse);
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
        
        // PRIORIDADE: Se tem role "Comissão", trata como Comissão mesmo que tenha permissão RH
        $isComissao = $user && $user->roles->pluck('name')->contains('Comissão');
        
        // Para RH (que não é Comissão), status padrão é 'aberto'. Para Comissão, sem filtro padrão (todos os status)
        $status = $request->get('status');
        if (!$status && $isRH && !$isComissao) {
            $status = 'aberto'; // Apenas RH puro vê recursos abertos por padrão
        }

        $query = EvaluationRecourse::with([
            'person',
            'responsiblePersons',
        ]);

        // Se é Comissão OU se não é RH, filtra apenas pelos recursos que a pessoa é responsável
        if ($isComissao || !$isRH) {
            if (!$person) {
                return redirect()->route('dashboard')->with('error', 'Dados de pessoa não encontrados.');
            }
            
            // Filtra apenas recursos onde a pessoa é responsável
            $query->whereHas('responsiblePersons', function($q) use ($person) {
                $q->where('person_id', $person->id);
            });
        }

        $recourses = $query
            ->when($status, fn($q) => $q->where('status', $status))
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
            'stage' => 'comissao',
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
            'logs' => fn($q) => $q->orderBy('created_at'),
        ]);

        return inertia('Recourses/Show', [
            'recourse' => [
                'id' => $recourse->id,
                'text' => $recourse->text,
                'status' => $recourse->status,
                'stage' => $recourse->stage,
                'response' => $recourse->response,
                'responded_at' => optional($recourse->responded_at)?->format('Y-m-d'),
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
                'commission' => [
                    'decision' => $recourse->commission_decision,
                    'response' => $recourse->commission_response,
                    'decided_at' => optional($recourse->commission_decided_at)?->format('Y-m-d H:i'),
                ],
                'director' => [
                    'decision' => $recourse->director_decision,
                    'response' => $recourse->director_response,
                    'decided_at' => optional($recourse->director_decided_at)?->format('Y-m-d H:i'),
                ],
                'secretary' => [
                    'decision' => $recourse->secretary_decision,
                    'response' => $recourse->secretary_response,
                    'decided_at' => optional($recourse->secretary_decided_at)?->format('Y-m-d H:i'),
                ],
            ],
            'permissions' => [
                'isRH' => $isRH,
                'isComissao' => $isComissao,
                'isRequerente' => $isRequerente,
            ],
        ]);
    }

    public function review(EvaluationRecourse $recourse)
    {
        // Verifica se a pessoa tem permissão (RH ou é responsável pelo recurso)
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
                'stage' => $recourse->stage,
                'text' => $recourse->text,
                'response' => $recourse->response,
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
                'commission' => [
                    'decision' => $recourse->commission_decision,
                    'response' => $recourse->commission_response,
                    'decided_at' => optional($recourse->commission_decided_at)?->format('Y-m-d H:i'),
                ],
                'director' => [
                    'decision' => $recourse->director_decision,
                    'response' => $recourse->director_response,
                    'decided_at' => optional($recourse->director_decided_at)?->format('Y-m-d H:i'),
                ],
                'secretary' => [
                    'decision' => $recourse->secretary_decision,
                    'response' => $recourse->secretary_response,
                    'decided_at' => optional($recourse->secretary_decided_at)?->format('Y-m-d H:i'),
                ],
            ],
            'availablePersons' => $availablePersons,
            'canManageAssignees' => $canManageAssignees, // Apenas RH puro pode gerenciar responsáveis
            'userRole' => $isComissao ? 'Comissão' : ($isRH ? 'RH' : 'Sem permissão'), // Para debug/informação
            'permissions' => [
                'isRH' => $isRH,
                'isComissao' => $isComissao,
                'isRequerente' => $isRequerente,
            ],
        ]);
    }

    public function markAnalyzing(EvaluationRecourse $recourse)
    {
        // Verifica se o usuário pode marcar como analisando (RH ou responsável pelo recurso)
        if (!$this->canAccessRecourse($recourse)) {
            return redirect()->back()->with('error', 'Você não tem permissão para marcar este recurso como em análise.');
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
        // Verifica se o usuário pode responder (RH ou responsável pelo recurso)
        if (!$this->canAccessRecourse($recourse)) {
            return redirect()->back()->with('error', 'Você não tem permissão para responder este recurso.');
        }

        $validated = $request->validate([
            'status' => ['required', 'in:respondido,indeferido'],
            'response' => ['required', 'string', 'min:5'],
            'response_attachments.*' => ['file', 'max:10240'], // Máximo 10MB por arquivo
        ]);

        // Comissão decide
        $recourse->update([
            'status' => $validated['status'],
            'response' => $validated['response'],
            'responded_at' => now(),
            'commission_decision' => $validated['status'] === 'respondido' ? 'deferido' : 'indeferido',
            'commission_response' => $validated['response'],
            'commission_decided_at' => now(),
            'stage' => 'diretoria_rh',
        ]);

        $recourse->logs()->create([
            'status' => $validated['status'],
            'message' => 'Parecer da Comissão registrado.',
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

        return redirect()
            ->back()
            ->with('success', 'Parecer salvo com sucesso!');
    }

    /**
     * Diretoria do RH homologa (deferir/indeferir) decisão da comissão
     */
    public function directorDecision(Request $request, EvaluationRecourse $recourse)
    {
        if (!$this->isRH()) {
            return redirect()->back()->with('error', 'Apenas a Diretoria do RH pode registrar esta decisão.');
        }

        if ($recourse->stage !== 'diretoria_rh') {
            return redirect()->back()->with('error', 'Etapa inválida para decisão da Diretoria.');
        }

        $validated = $request->validate([
            'decision' => ['required', 'in:deferido,indeferido'],
            'response' => ['required', 'string', 'min:5'],
        ]);

        $recourse->update([
            'director_decision' => $validated['decision'],
            'director_response' => $validated['response'],
            'director_decided_at' => now(),
            'stage' => 'requerente',
        ]);

        $recourse->logs()->create([
            'status' => 'diretoria_decidiu',
            'message' => 'Decisão da Diretoria do RH registrada: ' . strtoupper($validated['decision']),
        ]);

        return back()->with('success', 'Decisão da Diretoria registrada. Aguarda ciência do requerente.');
    }

    /**
     * Requerente toma ciência (1ª instância ou decisão final)
     */
    public function acknowledge(Request $request, EvaluationRecourse $recourse)
    {
        $user = Auth::user();
        $person = Person::where('cpf', $user->cpf)->first();

        if (!$person || $recourse->person_id !== $person->id) {
            return redirect()->back()->with('error', 'Apenas o requerente pode registrar ciência.');
        }

        if ($recourse->stage === 'requerente') {
            $recourse->update(['ack_first_at' => now()]);
            $recourse->logs()->create([
                'status' => 'ciencia_requerente_primeira',
                'message' => 'Requerente registrou ciência da decisão da 1ª instância.',
            ]);

            return back()->with('success', 'Ciência registrada. Você pode interpor recurso em 2ª instância se desejar.');
        }

        if ($recourse->stage === 'finalizado') {
            $recourse->update(['ack_final_at' => now()]);
            $recourse->logs()->create([
                'status' => 'ciencia_requerente_final',
                'message' => 'Requerente registrou ciência da decisão final.',
            ]);

            return back()->with('success', 'Ciência final registrada. Processo concluído.');
        }

        return back()->with('error', 'Etapa atual não permite ciência.');
    }

    /**
     * RH encaminha 2ª instância ao Secretário após o requerente interpor novo recurso
     */
    public function escalateToSecretary(EvaluationRecourse $recourse)
    {
        if (!$this->isRH()) {
            return back()->with('error', 'Apenas o RH pode encaminhar à 2ª instância.');
        }
        if ($recourse->stage !== 'requerente') {
            return back()->with('error', 'Etapa inválida para encaminhar à 2ª instância.');
        }

        $recourse->update(['stage' => 'secretario']);
        $recourse->logs()->create([
            'status' => 'encaminhado_secretario',
            'message' => 'RH encaminhou o processo ao Secretário para análise de 2ª instância.',
        ]);

        return back()->with('success', 'Processo encaminhado ao Secretário.');
    }

    /**
     * Secretário decide a 2ª instância
     */
    public function secretaryDecision(Request $request, EvaluationRecourse $recourse)
    {
        // Reutilizamos a permissão de RH para o Secretário; em instalações reais, usar role específica
        if (!$this->isRH()) {
            return back()->with('error', 'Apenas o Secretário pode registrar esta decisão.');
        }
        if ($recourse->stage !== 'secretario') {
            return back()->with('error', 'Etapa inválida para decisão do Secretário.');
        }

        $validated = $request->validate([
            'decision' => ['required', 'in:deferido,indeferido'],
            'response' => ['required', 'string', 'min:5'],
        ]);

        $recourse->update([
            'secretary_decision' => $validated['decision'],
            'secretary_response' => $validated['response'],
            'secretary_decided_at' => now(),
            'stage' => 'finalizado',
        ]);

        $recourse->logs()->create([
            'status' => 'secretario_decidiu',
            'message' => 'Decisão do Secretário registrada: ' . strtoupper($validated['decision']),
        ]);

        return back()->with('success', 'Decisão final registrada. Aguarda ciência do requerente.');
    }

    /**
     * Devolver o processo à instância anterior
     */
    public function returnToPrevious(Request $request, EvaluationRecourse $recourse)
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $from = $recourse->stage;
        $to = null;
        switch ($from) {
            case 'comissao':
                $to = 'rh';
                break;
            case 'diretoria_rh':
                $to = 'comissao';
                break;
            case 'requerente':
                $to = 'diretoria_rh';
                break;
            case 'secretario':
                $to = 'requerente';
                break;
            case 'finalizado':
                return back()->with('error', 'Processo finalizado não pode ser devolvido.');
            case 'rh':
                return back()->with('error', 'Já está na primeira instância do fluxo.');
        }

        if (!$to) {
            return back()->with('error', 'Não foi possível determinar a instância anterior.');
        }

        // Permissões: quem está na instância atual pode devolver
        // Para simplificar, exigimos permissão de RH ou ser responsável (comissão)
        if (!$this->isRH() && !$this->isResponsibleForRecourse($recourse)) {
            return back()->with('error', 'Você não tem permissão para devolver este processo.');
        }

        $recourse->update(['stage' => $to]);
        $recourse->logs()->create([
            'status' => 'devolvido',
            'message' => 'Processo devolvido da etapa ' . $from . ' para ' . $to . ($validated['reason'] ? ('. Motivo: ' . $validated['reason']) : ''),
        ]);

        return back()->with('success', 'Processo devolvido para a instância anterior.');
    }

    public function assignResponsible(Request $request, EvaluationRecourse $recourse)
    {
        $user = Auth::user();
        $isRH = $this->isRH();
        $isComissao = $user && $user->roles->pluck('name')->contains('Comissão');
        
        // Apenas RH puro (que não é Comissão) pode atribuir responsáveis
        if (!$isRH || $isComissao) {
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
        
        // Apenas RH puro (que não é Comissão) pode remover responsáveis
        if (!$isRH || $isComissao) {
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
