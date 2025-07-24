<?php

namespace App\Http\Controllers;

use App\Models\configs as Config; 
use Illuminate\Http\Request;
use Inertia\Inertia;

class ConfigController extends Controller
{
    /**
     * Exibe a página de configurações, carregando os dados existentes.
     * Este método deve ser o que renderiza sua view Configs.vue
     */
    public function index()
    {
        // ... (seu código existente para buscar 'forms', 'existingYears', etc.)

        return Inertia::render('Configs', [
            // ... (suas props existentes)
            'configs' => Config::first(),
        ]);
    }

    /**
     * Salva as configurações no banco de dados.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'gradesPeriod' => 'required|date',
            'awarePeriod' => 'required|integer|min:0',
            'recoursePeriod' => 'required|integer|min:0',
        ]);

        // Usa updateOrCreate para criar a configuração se não existir, ou atualizá-la se já existir.
        // O primeiro array é para encontrar o registro (aqui, um array vazio, pois sempre atualizaremos o primeiro).
        // O segundo array são os dados a serem atualizados ou criados.
        Config::updateOrCreate(['year' => $validatedData['year']], $validatedData);

        return back()->with('success', 'Configurações salvas com sucesso!');
    }
}