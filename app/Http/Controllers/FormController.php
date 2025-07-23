<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\GroupQuestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Carbon\Carbon;

class FormController extends Controller
{
    /**
     * Display a listing of the resource.
     * (Esta é a nossa página de Configurações)
     */
    public function index()
    {
        // Carrega todos os formulários com suas questões e passa para a view
        $forms = Form::with('groupQuestions.questions')->latest()->get();

        return Inertia::render('Dashboard/Configs', [
            'forms' => $forms,
        ]);
    }

    public function show(Form $formulario)
    {
        $formulario->load('groupQuestions.questions');

        // Linha correta, sem o prefixo 'pages/'
        return Inertia::render('Dashboard/FormViewPage', [
            'form' => $formulario,
        ]);
    }

    public function create(Request $request)
{
    $validated = $request->validate([
        'type' => ['required', Rule::in([
            'gestor', 'chefia', 'servidor', 'comissionado',
            'pactuacao_servidor', 'pactuacao_comissionado', 'pactuacao_gestor' 
        ])],
        'year' => 'required|digits:4',
    ]);

        
    $pdiTypes = ['pactuacao_servidor', 'pactuacao_comissionado', 'pactuacao_gestor'];

    if (in_array($validated['type'], $pdiTypes)) {
        
        return Inertia::render('Dashboard/PdiFormPage', [
            'formType' => $validated['type'],
            'year' => $validated['year'],
        ]);
    }

   
    return Inertia::render('Dashboard/FormPage', [
        'formType' => $validated['type'],
        'year' => $validated['year'],
    ]);
}

    public function edit(Form $formulario)
{
    $formulario->load('groupQuestions.questions');

    
    $pdiTypes = ['pactuacao_servidor', 'pactuacao_comissionado', 'pactuacao_gestor'];
    
    if (in_array($formulario->type, $pdiTypes)) {
       
        return Inertia::render('Dashboard/PdiFormPage', [
            'form' => $formulario,
            'formType' => $formulario->type,
            'year' => $formulario->year,
        ]);
    }
    
    
    return Inertia::render('Dashboard/FormPage', [
        'form' => $formulario,
        'formType' => $formulario->type,
        'year' => $formulario->year,
    ]);
}

    /**
     * Update the specified resource in storage.
     * (Salva as alterações do formulário)
     */
    public function update(Request $request, Form $formulario)
    {
        $validatedData = $request->validate([
            'title' => 'required|string|max:100',
            'groups' => 'required|array|min:1',
            'groups.*.name' => 'required|string|max:150',
            'groups.*.weight' => 'required|numeric|min:0',
            'groups.*.questions' => 'required|array|min:1',
            'groups.*.questions.*.text' => 'required|string',
            'groups.*.questions.*.weight' => 'required|numeric|min:0',
        ]);

        // CORREÇÃO: Validação 1 - Soma dos pesos dos GRUPOS
        $totalGroupWeight = collect($validatedData['groups'])->sum('weight');
        if (abs($totalGroupWeight - 100) > 0.01) {
            return back()->withErrors(['groups' => 'A soma dos pesos de todos os GRUPOS deve ser exatamente 100%.'])->withInput();
        }

        // CORREÇÃO: Validação 2 - Soma dos pesos das QUESTÕES dentro de cada grupo
        foreach ($validatedData['groups'] as $groupData) {
            $totalRelativeWeight = collect($groupData['questions'])->sum('weight');
            if (abs($totalRelativeWeight - 100) > 0.01) {
                return back()->withErrors(['groups' => "A soma dos pesos das questões no grupo '{$groupData['name']}' deve ser 100%."])->withInput();
            }
        }

        DB::transaction(function () use ($validatedData, $formulario) {
            $formulario->update(['name' => $validatedData['title']]);

            $formulario->groupQuestions()->delete();

            foreach ($validatedData['groups'] as $groupData) {
                $groupQuestion = $formulario->groupQuestions()->create([
                    'name' => $groupData['name'],
                    'weight' => $groupData['weight']
                ]);

                foreach ($groupData['questions'] as $questionData) {
                    $finalWeight = ($groupData['weight'] / 100.0) * $questionData['weight'];
                    $groupQuestion->questions()->create([
                        'text_content' => $questionData['text'],
                        'weight' => round($finalWeight, 2),
                    ]);
                }
            }
        });

        return redirect()->route('configs')->with('success', 'Formulário atualizado com sucesso!');
    }

    public function setPrazo(Request $request)
    {
        
        // 1. Validar as duas datas recebidas do formulário
        $validated = $request->validate([
            'year' => 'required|digits:4',
            'group' => ['required', Rule::in(['avaliacao', 'pdi'])],
            'term_first' => 'required|date',
            'term_end' => 'required|date|after_or_equal:term_first',
        ]);

        // 2. Definir para quais tipos de formulário a regra se aplica
        $formTypes = $validated['group'] === 'avaliacao'
            ? ['gestor', 'chefia', 'servidor', 'comissionado'] // Incluído 'servidor'
            : ['pactuacao_servidor', 'pactuacao_comissionado', 'pactuacao_gestor'];

        // 3. Atualizar todos os formulários do grupo de uma só vez
        Form::where('year', $validated['year'])
            ->whereIn('type', $formTypes)
            ->update([
                'term_first' => $validated['term_first'],
                'term_end' => $validated['term_end']
            ]);

        return back()->with('success', 'Prazo definido com sucesso!');
    }

    public function setLiberar(Request $request)
    {
        $validated = $request->validate([
            'year' => 'required|digits:4',
            'group' => ['required', Rule::in(['avaliacao', 'pdi'])],
        ]);

        $formTypes = $validated['group'] === 'avaliacao'
            ? ['gestor', 'chefia', 'servidor', 'comissionado']
            : ['pactuacao_servidor', 'pactuacao_comissionado', 'pactuacao_gestor'];

        Form::where('year', $validated['year'])
            ->whereIn('type', $formTypes)
            ->update([
                'release' => true,
                'release_data' => Carbon::now(),
            ]);

        return back()->with('success', 'Formulários liberados com sucesso!');
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'title' => 'required|string|max:100',
            'year' => 'required|digits:4',
            'type' => ['required', 'string', Rule::in(['gestor', 'chefia', 'comissionado', 'servidor'])],
            'groups' => 'required|array|min:1',
            'groups.*.name' => 'required|string|max:150',
            'groups.*.weight' => 'required|numeric|min:0', // Peso do grupo
            'groups.*.questions' => 'required|array|min:1',
            'groups.*.questions.*.text' => 'required|string',
            'groups.*.questions.*.weight' => 'required|numeric|min:0', // Peso relativo da questão
        ]);

        // Validação 1: Soma dos pesos dos grupos
        $totalGroupWeight = collect($validatedData['groups'])->sum('weight');
        if (abs($totalGroupWeight - 100) > 0.01) {
            return back()->withErrors(['groups' => 'A soma dos pesos de todos os grupos deve ser exatamente 100.'])->withInput();
        }

        // Validação 2: Soma dos pesos relativos em cada grupo
        foreach ($validatedData['groups'] as $groupData) {
            $totalRelativeWeight = collect($groupData['questions'])->sum('weight');
            if (abs($totalRelativeWeight - 100) > 0.01) {
                return back()->withErrors(['groups' => "A soma dos pesos das questões no grupo '{$groupData['name']}' deve ser 100."])->withInput();
            }
        }

        DB::transaction(function () use ($validatedData) {
            $form = Form::create([
                'name' => $validatedData['title'],
                'year' => $validatedData['year'],
                'type' => $validatedData['type'],
            ]);

            foreach ($validatedData['groups'] as $groupData) {
                $groupQuestion = $form->groupQuestions()->create([
                    'name' => $groupData['name'],
                    'weight' => $groupData['weight']
                ]);

                foreach ($groupData['questions'] as $questionData) {
                    // Calcula o peso final da questão para salvar no banco
                    $finalWeight = ($groupData['weight'] / 100.0) * ($questionData['weight']);

                    $groupQuestion->questions()->create([
                        'text_content' => $questionData['text'],
                        'weight' => round($finalWeight), // Salva o peso final calculado
                    ]);
                }
            }
        });

        return redirect()->route('configs')->with('success', 'Formulário criado com sucesso!');
    }

    /**
     * Salva um novo formulário de PDI (Pactuação), com lógica simplificada.
     */
    public function storePdi(Request $request)
    {
        
        // Validação simples, sem pesos
        $validatedData = $request->validate([
            'title' => 'required|string|max:100',
            'year' => 'required|digits:4',
            'type' => ['required', Rule::in(['pactuacao_servidor','pactuacao_comissionado','pactuacao_gestor'])],
            'groups' => 'required|array|min:1',
            'groups.*.name' => 'required|string|max:150',
            'groups.*.questions' => 'required|array|min:1',
            'groups.*.questions.*.text' => 'required|string',
        ]);

        DB::transaction(function () use ($validatedData) {
            $form = Form::create([
                'name' => $validatedData['title'],
                'year' => $validatedData['year'],
                'type' => $validatedData['type'],
            ]);

            foreach ($validatedData['groups'] as $groupData) {
                // Cria o grupo com peso 0 ou null
                $groupQuestion = $form->groupQuestions()->create([
                    'name' => $groupData['name'],
                    'weight' => 0
                ]);

                foreach ($groupData['questions'] as $questionData) {
                    // Cria a questão com peso 0 ou null
                    $groupQuestion->questions()->create([
                        'text_content' => $questionData['text'],
                        'weight' => 0,
                    ]);
                }
            }
        });

        return redirect()->route('configs')->with('success', 'Formulário PDI criado com sucesso!');
    }

    /**
     * Atualiza um formulário de PDI (Pactuação) existente.
     */
    public function updatePdi(Request $request, Form $formulario)
    {
        // Validação simples, sem pesos
        $validatedData = $request->validate([
            'title' => 'required|string|max:100',
            'groups' => 'required|array|min:1',
            'groups.*.name' => 'required|string|max:150',
            'groups.*.questions' => 'required|array|min:1',
            'groups.*.questions.*.text' => 'required|string',
        ]);

        DB::transaction(function () use ($validatedData, $formulario) {
            $formulario->update(['name' => $validatedData['title']]);

            // Deleta grupos e questões antigos para recriar
            $formulario->groupQuestions()->delete();

            foreach ($validatedData['groups'] as $groupData) {
                $groupQuestion = $formulario->groupQuestions()->create([
                    'name' => $groupData['name'],
                    'weight' => 0
                ]);

                foreach ($groupData['questions'] as $questionData) {
                    $groupQuestion->questions()->create([
                        'text_content' => $questionData['text'],
                        'weight' => 0,
                    ]);
                }
            }
        });

        return redirect()->route('configs')->with('success', 'Formulário PDI atualizado com sucesso!');
    }

}