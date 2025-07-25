<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateEvaluationsJob;
use App\Jobs\GeneratePdiJob;
use Illuminate\Http\RedirectResponse;
use Redirect;


class ReleaseController extends Controller
{
    
    /**
     * Dispara o job para gerar as avaliações do ano especificado.
     */
    public function generateRelease(string $year)
    {
        GenerateEvaluationsJob::dispatch($year);

        return back()->with('success', 'A geração das avaliações foi iniciada em segundo plano!');
    }

    /**
     * Dispara o job para gerar os PDIs do ano especificado.
     * ESTE É O NOVO MÉTODO
     */
    public function generatePdi(string $year)
    {
        GeneratePdiJob::dispatch($year);

        return back()->with('success', 'A geração dos PDIs foi iniciada em segundo plano!');
    }
}