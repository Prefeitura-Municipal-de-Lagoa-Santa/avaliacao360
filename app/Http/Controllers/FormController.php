<?php

namespace App\Http\Controllers;

use App\Models\Form;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class FormController extends Controller
{
    /**
     * Display a listing of the resource.
     * (Esta é a nossa página de Configurações)
     */
    public function index()
    {
        // Carrega todos os formulários com suas questões e passa para a view
        $forms = Form::with('questions')->latest()->get();

        return Inertia::render('Dashboard/Configs', [
            'forms' => $forms,
        ]);
    }

    public function create(Request $request)
    {
        // Valida se o tipo e ano foram passados na URL
        $validated = $request->validate([
            'type' => ['required', Rule::in(['autoavaliacao', 'chefia', 'pactuacao', 'metas'])],
            'year' => 'required|digits:4',
        ]);

        return Inertia::render('Dashboard/FormPage', [
            'formType' => $validated['type'],
            'year' => $validated['year'],
        ]);
    }

    public function edit(Form $formulario)
    {
        $formulario->load('questions');
        // Renderiza a MESMA PÁGINA, mas agora passando o formulário
        return Inertia::render('Dashboard/FormPage', [
            'form' => $formulario,
            'formType' => $formulario->type, // Passa os dados existentes
            'year' => $formulario->year,
        ]);
    }
    /**
     * Update the specified resource in storage.
     * (Salva as alterações do formulário)
     */
    public function update(Request $request, Form $formulario)
    {
        // A validação é muito parecida com a de 'store'
        $validatedData = $request->validate([
            'title' => 'required|string|max:100',
            'year' => 'required|digits:4',
            'questions' => 'required|array|min:1',
            'questions.*.text' => 'required|string',
            'questions.*.weight' => 'required|integer|min:0|max:100',
        ]);

        $totalWeight = collect($validatedData['questions'])->sum('weight');
        if ($totalWeight !== 100) {
            return back()->withErrors(['questions' => 'A soma dos pesos deve ser exatamente 100.'])->withInput();
        }

        DB::transaction(function () use ($validatedData, $formulario) {
            // Atualiza os dados do formulário principal
            $formulario->update([
                'name' => $validatedData['title'],
                'year' => $validatedData['year'],
            ]);

            // Sincroniza as questões: a forma mais simples é deletar as antigas e criar as novas
            $formulario->questions()->delete();

            foreach ($validatedData['questions'] as $questionData) {
                $formulario->questions()->create([
                    'text_content' => $questionData['text'],
                    'weight' => $questionData['weight'],
                ]);
            }
        });

        return redirect()->route('configs')->with('success', 'Ação realizada com sucesso!');
    }

    /**
     * Remove the specified resource from storage.
     * (Exclui o formulário)
     */
    public function destroy(Form $formulario)
    {
        $formulario->delete();
        return redirect()->route('configs')->with('success', 'Ação realizada com sucesso!');
    }

    // O seu método store() continua aqui, sem alterações...
    public function store(Request $request)
    { /* ... seu código ... */
    }
}