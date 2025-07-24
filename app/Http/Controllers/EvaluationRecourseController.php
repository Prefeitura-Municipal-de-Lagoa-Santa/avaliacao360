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
        $recourse->load(['evaluation.form', 'attachments', 'person', 'user']);

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
                    'year' => $recourse->evaluation->form->year ?? null,
                    'id' => $recourse->evaluation->id,
                ],
            ],
        ]);
    }

}
