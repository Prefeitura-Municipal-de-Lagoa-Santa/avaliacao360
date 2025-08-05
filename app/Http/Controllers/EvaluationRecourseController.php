<?php

namespace App\Http\Controllers;

use App\Models\EvaluationRecourse;
use App\Models\EvaluationRecourseAssignee;
use App\Models\EvaluationRecourseAttachment;
use App\Models\EvaluationRecourseResponseAttachment;
use App\Models\EvaluationRequest;
use App\Models\Person;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        // DEBUG: Ver qual avaliação está sendo enviada como "do chefe"
        dd([
            'recourse_id' => $recourse->id,
            'evaluation_full' => $recourse->evaluation->evaluation,
            'evaluation_type' => $recourse->evaluation->evaluation->type,
            'evaluation_answers' => $recourse->evaluation->evaluation->answers,
            'form_info' => $recourse->evaluation->evaluation->form,
            'evaluated_person' => $recourse->evaluation->evaluation->evaluatedPerson,
        ]);

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
                    'id' => $recourse->evaluation->evaluation->id,
                    'year' => optional($recourse->evaluation->evaluation->form)->year ?? '—',
                    'type' => $recourse->evaluation->evaluation->type ?? '—',
                    'form_name' => $recourse->evaluation->evaluation->form->name ?? '—',
                    'avaliado' => $recourse->evaluation->evaluation->evaluatedPerson->name ?? '—',
                    'answers' => $recourse->evaluation->evaluation->answers->map(fn($a) => [
                        'question' => $a->question->text ?? '',
                        'score' => $a->score,
                    ]),
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
