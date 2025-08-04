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
    public function index(Request $request)
    {
        $user = Auth::user();
        $person = Person::where('cpf', $user->cpf)->first();
        
        $status = $request->get('status', 'aberto');

        $query = EvaluationRecourse::with([
            'person',
            'evaluation.evaluation.form',
            'responsiblePersons',
        ]);

        // Se não é RH (que tem permissão total), filtra apenas pelos recursos que a pessoa é responsável
        if (!user_can('recourse')) {
            if (!$person) {
                return redirect()->route('dashboard')->with('error', 'Dados de pessoa não encontrados.');
            }
            
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
                        'id' => $recourse->evaluation->id,
                        'year' => optional($recourse->evaluation->evaluation->form)->year ?? '—',
                    ],
                    'responsible_persons' => $recourse->responsiblePersons->map(fn($p) => [
                        'name' => $p->name,
                        'registration_number' => $p->registration_number,
                    ]),
                ];
            })
            ->withQueryString();

        return inertia('Recourses/Index', [
            'recourses' => $recourses,
            'status' => $status,
            'canManageAssignees' => user_can('recourse'), // RH pode gerenciar responsáveis
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
        $user = Auth::user();
        $person = Person::where('cpf', $user->cpf)->first();
        
        // Verifica se a pessoa tem permissão (RH ou é responsável pelo recurso)
        $canAccess = user_can('recourse') || 
                    ($person && $recourse->responsiblePersons()->where('person_id', $person->id)->exists());
        
        if (!$canAccess) {
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

        // Busca apenas pessoas com role "Comissão" para poder atribuir responsáveis (apenas RH)
        $availablePersons = user_can('recourse') 
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
            'canManageAssignees' => user_can('recourse'),
        ]);
    }

    public function markAnalyzing(EvaluationRecourse $recourse)
    {
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
        if (!user_can('recourse')) {
            return redirect()->back()->with('error', 'Você não tem permissão para atribuir responsáveis.');
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

        $user = Auth::user();
        $assignedBy = Person::where('cpf', $user->cpf)->first();

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
        if (!user_can('recourse')) {
            return redirect()->back()->with('error', 'Você não tem permissão para remover responsáveis.');
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
