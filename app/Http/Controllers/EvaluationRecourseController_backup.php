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
        ]);

        // Calcular média geral ponderada
        $evaluationsCollection = $allEvaluations->map(function ($evaluation) use ($evaluationRequests) {
            $answers = $evaluation->answers;
            $validScores = $answers->whereNotNull('score')->pluck('score');
            $requests = $evaluationRequests->get($evaluation->id, collect());
            $isTeamEvaluation = $evaluation->type === 'chefia' && $requests->count() > 0;
            
            return [
                'type' => $evaluation->type,
                'is_team_evaluation' => $isTeamEvaluation,
                'average' => $validScores->count() > 0 ? round($validScores->avg(), 1) : null,
            ];
        });

        // Identificar os tipos de avaliação disponíveis
        $chefeAvg = null;
        $equipeAvg = null;
        $autoAvg = null;

        foreach ($evaluationsCollection as $eval) {
            if (in_array($eval['type'], ['gestor', 'comissionado']) && $eval['average'] !== null) {
                $chefeAvg = $eval['average'];
            } elseif ($eval['is_team_evaluation'] && $eval['average'] !== null) {
                $equipeAvg = $eval['average'];
            } elseif (in_array($eval['type'], ['auto', 'autoavaliaçãoGestor', 'autoavaliaçãoComissionado']) && $eval['average'] !== null) {
                $autoAvg = $eval['average'];
            }
        }

        // Calcular média geral com pesos
        $mediaGeral = null;
        if ($chefeAvg !== null && $autoAvg !== null) {
            if ($equipeAvg !== null) {
                // Tem equipe: 50% chefe + 25% equipe + 25% auto
                $mediaGeral = round(($chefeAvg * 0.5) + ($equipeAvg * 0.25) + ($autoAvg * 0.25), 1);
            } else {
                // Sem equipe: 70% chefe + 30% auto
                $mediaGeral = round(($chefeAvg * 0.7) + ($autoAvg * 0.3), 1);
            }
        }

        return inertia('Recourses/PersonEvaluations', [
            'recourse' => [
                'id' => $recourse->id,
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
                
                $isChefiaType = $evaluation->type === 'chefia';
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
                    }
                } else {
                    // Para avaliações individuais, encontra o avaliador
                    if ($requests->count() > 0) {
                        $request = $requests->first();
                        if ($request->requester) {
                            $evaluatorName = $request->requester->name;
                        } elseif ($request->requested) {
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
            'media_geral' => $mediaGeral,
        ]);
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
            'logs' => fn($q) => $q->orderBy('created_at'),
        ]);

        return inertia('Recourses/Show', [
            'recourse' => [
                'id' => $recourse->id,
                'text' => $recourse->text,
                'status' => $recourse->status,
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
            ],
        ]);
    }

    public function review(EvaluationRecourse $recourse)
    {
        // Verifica se a pessoa tem permissão (RH ou é responsável pelo recurso)
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
            ],
            'availablePersons' => $availablePersons,
            'canManageAssignees' => $canManageAssignees, // Apenas RH puro pode gerenciar responsáveis
            'userRole' => $isComissao ? 'Comissão' : ($isRH ? 'RH' : 'Sem permissão'), // Para debug/informação
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
            'response_attachments.*' => ['file', 'max:102400'], // Máximo 100MB por arquivo
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

        return redirect()
            ->back()
            ->with('success', 'Parecer salvo com sucesso!');
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
