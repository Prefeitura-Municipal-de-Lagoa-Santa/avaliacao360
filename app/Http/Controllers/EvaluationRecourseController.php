<?php

namespace App\Http\Controllers;

use App\Models\EvaluationRecourse;
use App\Models\EvaluationRecourseAttachment;
use App\Models\EvaluationRequest;
use App\Models\Person;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class EvaluationRecourseController extends Controller
{

    public function index(Request $request)
    {
        if (!user_can('recourse')) {
            return redirect()->route('dashboard')->with('error', 'Você não tem permissão.');
        }

        $status = $request->get('status', 'aberto');

        $recourses = EvaluationRecourse::with([
            'person',
            'evaluation.evaluation.form',
        ])
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
                ];
            })
            ->withQueryString();

        return inertia('Recourses/Index', [
            'recourses' => $recourses,
            'status' => $status,
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
            'attachments.*' => 'file|max:10240', // 10MB por arquivo
        ]);
        $user = Auth::user();
        $person = Person::where('cpf', $user->cpf)->first();
        $personId = $person->id;

        $recourse = EvaluationRecourse::create([
            'evaluation_id' => $evaluationId,
            'person_id' => $personId,
            'text' => $request->text,
            'status' => 'aberto',
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

        return back()->with('success', 'Recurso enviado com sucesso!');
    }

    public function show(EvaluationRecourse $recourse)
    {
        $recourse->load([
            'evaluation.evaluation.form', // 👈 observe os 2 níveis!
            'attachments',
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
                    'url' => Storage::disk('public')->url($a->file_path),
                ]),
                'evaluation' => [
                    'year' => optional($recourse->evaluation->evaluation->form)->year_formatted ?? '—',
                    'id' => $recourse->evaluation->id,
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
        $recourse->load([
            'evaluation.evaluation.form',
            'evaluation.evaluation.evaluatedPerson',
            'evaluation.evaluation.answers.question',
            'attachments',
            'person',
            'logs' => fn($q) => $q->orderBy('created_at'),
        ]);

        return inertia('Recourses/Review', [
            'recourse' => [
                'id' => $recourse->id,
                'status' => $recourse->status,
                'text' => $recourse->text,
                'attachments' => $recourse->attachments->map(fn($a) => [
                    'name' => $a->original_name,
                    'url' => Storage::disk('public')->url($a->file_path),
                ]),
                'person' => [
                    'name' => $recourse->person->name,
                ],
                'evaluation' => [
                    'id' => $recourse->evaluation->evaluation->id,
                    'year' => optional($recourse->evaluation->evaluation->form)->year_formatted ?? '—',
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
        ]);
    }
}
