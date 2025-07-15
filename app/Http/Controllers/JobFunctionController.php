<?php

namespace App\Http\Controllers;

use App\Models\JobFunction;
use Illuminate\Http\Request;
use Inertia\Inertia;

class JobFunctionController extends Controller
{
    // Listagem de funções não chefes para gerenciamento de grupo/type
    public function index()
    {
        // Agora pega TODAS as funções, não só não-chefes
        $jobFunctions = JobFunction::orderBy('name')->get();

        $types = [
            'chefe' => 'Gestor',
            'comissionado' => 'Comissionados',
        ];

        return Inertia::render('JobFunctions/Index', [
            'jobFunctions' => $jobFunctions,
            'types' => $types,
        ]);
    }


    public function updateType(Request $request, $id)
    {
        $jobFunction = JobFunction::findOrFail($id);

        $types = ['chefe', 'comissionado', 'assessor_2']; // ou os types permitidos

        $request->validate([
            'type' => 'required|string|in:' . implode(',', $types),
        ]);

        $jobFunction->type = $request->type;

        // Se não for o grupo chefe, sempre is_manager = false
        if ($request->type !== 'chefe') {
            $jobFunction->is_manager = false;
        }

        $jobFunction->save();

        return back()->with('success', 'Grupo (type) atualizado!');
    }

}
