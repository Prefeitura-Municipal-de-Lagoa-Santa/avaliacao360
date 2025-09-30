<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Models\EvaluationRecourse;
use App\Models\Person;

Route::get('/debug-recourse-query', function() {
    $user = Auth::user();
    if (!$user) {
        return response()->json(['error' => 'Usuário não logado']);
    }
    
    $person = Person::where('cpf', $user->cpf)->first();
    if (!$person) {
        return response()->json(['error' => 'Pessoa não encontrada']);
    }
    
    // Simula exatamente o que o controller faz
    $isRH = user_can('recourse');
    $status = request()->get('status'); // Pega parâmetro da URL
    
    if (!$status && $isRH) {
        $status = 'aberto'; // RH vê recursos abertos por padrão
    }
    
    // CONSULTA 1: Como no Dashboard (simples)
    \DB::enableQueryLog();
    $dashboardQuery = EvaluationRecourse::whereHas('responsiblePersons', function($q) use ($person) {
        $q->where('person_id', $person->id);
    });
    $dashboardResults = $dashboardQuery->get();
    $dashboardQueries = \DB::getQueryLog();
    \DB::flushQueryLog();
    
    // CONSULTA 2: Como na listagem (com eager loading)
    \DB::enableQueryLog();
    $listingQuery = EvaluationRecourse::with([
        'person',
        'evaluation.evaluation.form',
        'responsiblePersons',
    ])->whereHas('responsiblePersons', function($q) use ($person) {
        $q->where('person_id', $person->id);
    });
    
    // Aplica filtro de status se houver
    if ($status) {
        $listingQuery->where('status', $status);
    }
    
    $listingResults = $listingQuery->latest()->get();
    $listingQueries = \DB::getQueryLog();
    
    return response()->json([
        'user' => [
            'name' => $user->name,
            'cpf' => $user->cpf,
        ],
        'person' => [
            'id' => $person->id,
            'name' => $person->name,
        ],
        'isRH' => $isRH,
        'status_filter' => $status,
        'dashboard_query' => [
            'count' => $dashboardResults->count(),
            'results' => $dashboardResults->map(fn($r) => [
                'id' => $r->id,
                'status' => $r->status,
            ]),
            'sql' => $dashboardQueries,
        ],
        'listing_query' => [
            'count' => $listingResults->count(),
            'results' => $listingResults->map(fn($r) => [
                'id' => $r->id,
                'status' => $r->status,
                'person_loaded' => $r->relationLoaded('person'),
                'evaluation_loaded' => $r->relationLoaded('evaluation'),
                'responsible_loaded' => $r->relationLoaded('responsiblePersons'),
            ]),
            'sql' => $listingQueries,
        ],
    ]);
});
