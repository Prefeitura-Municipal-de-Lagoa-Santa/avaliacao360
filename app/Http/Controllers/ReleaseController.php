<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateEvaluationsJob;
use Illuminate\Http\RedirectResponse;
use Redirect;


class ReleaseController extends Controller
{
    public function generateRelease(string $year): RedirectResponse
    {
        // Dispara o job, passando o ano como parâmetro.
        GenerateEvaluationsJob::dispatch($year);

        // Retorna imediatamente para o usuário com uma mensagem de sucesso.
        return Redirect::route('configs')->with('success', 'A geração das avaliações foi iniciada! O processo será executado em segundo plano.');
    }
}