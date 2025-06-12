<?php

namespace App\Http\Controllers;

use App\Models\Form;
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
        $forms = Form::with('questions')->latest()->get();

        return Inertia::render('Dashboard/Configs', [
            'forms' => $forms,
        ]);
    }

    public function show(Form $formulario)
{
    $formulario->load('questions');

    // Linha correta, sem o prefixo 'pages/'
    return Inertia::render('Dashboard/FormViewPage', [
        'form' => $formulario,
    ]);
}

    public function create(Request $request)
    {
        // Valida se o tipo e ano foram passados na URL
        $validated = $request->validate([
            'type' => ['required', Rule::in(['autoavaliacao', 'chefia', 'servidor', 'pactuacao', 'metas'])],
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
        ? ['autoavaliacao', 'chefia', 'servidor'] // Incluído 'servidor'
        : ['pactuacao', 'metas'];

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
        ? ['autoavaliacao', 'chefia', 'servidor']
        : ['pactuacao'];

    Form::where('year', $validated['year'])
        ->whereIn('type', $formTypes)
        ->update([
            'release' => true,
            'release_data' => Carbon::now(),
        ]);

    return back()->with('success', 'Formulários liberados com sucesso!');
}


    // O seu método store() continua aqui, sem alterações...
    public function store(Request $request)
{
    $validatedData = $request->validate([
        'title' => 'required|string|max:100',
        'year' => 'required|digits:4',
        'type' => ['required', 'string', Rule::in(['autoavaliacao', 'chefia', 'servidor', 'pactuacao'])],
        'questions' => 'required|array|min:1',
        'questions.*.text' => 'required|string',
        'questions.*.weight' => 'required|integer|min:0|max:100',
    ]);

       


    // 2. Validação do peso total
    $totalWeight = collect($validatedData['questions'])->sum('weight');
    if ($totalWeight !== 100) {
        // Retorna para a página anterior com um erro
        return back()->withErrors(['questions' => 'A soma dos pesos de todas as questões deve ser exatamente 100.'])->withInput();
    }

    // 3. Lógica de criação no banco de dados
    DB::transaction(function () use ($validatedData) {
        // Cria o formulário principal
        $form = Form::create([
            'name' => $validatedData['title'],
            'year' => $validatedData['year'],
            'type' => $validatedData['type'],
        ]);

        // Cria as questões associadas
        foreach ($validatedData['questions'] as $questionData) {
            $form->questions()->create([
                'text_content' => $questionData['text'],
                'weight' => $questionData['weight'],
            ]);
        }
    });

    // 4. Redireciona para a página de configurações com mensagem de sucesso
    return redirect()->route('configs')->with('success', 'Formulário criado com sucesso!');
}
}