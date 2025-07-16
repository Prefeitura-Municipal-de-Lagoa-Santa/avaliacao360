<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Form;
use App\Models\Evaluation;
use App\Jobs\GenerateEvaluationsJob;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AutoGenerateEvaluations extends Command
{
    /**
     * O nome e a assinatura do comando do console.
     *
     * @var string
     */
    protected $signature = 'evaluations:generate-auto';

    /**
     * A descrição do comando do console.
     *
     * @var string
     */
    protected $description = 'Verifica se as avaliações de um período já foram geradas. Se não foram, e a data de início é hoje, dispara o Job de geração.';

    /**
     * Executa o comando do console.
     */
    public function handle(): void
    {
        $this->info('Iniciando verificação para geração automática de avaliações...');
        Log::info('Iniciando tarefa agendada: evaluations:generate-auto');

        $today = Carbon::today()->toDateString();

        // 1. Encontra todos os formulários que foram liberados e cujo prazo de início é hoje.
        $formsParaVerificar = Form::where('release', true)
            ->where('term_first', $today)
            ->get();

        if ($formsParaVerificar->isEmpty()) {
            $this->info('Nenhum período de avaliação programado para iniciar hoje.');
            Log::info('Tarefa agendada finalizada: Nenhum período de avaliação encontrado para hoje.');
            return;
        }

        // Agrupa por ano para tratar cada ciclo de avaliação separadamente
        $anosParaVerificar = $formsParaVerificar->pluck('year')->unique();

        foreach ($anosParaVerificar as $year) {
            $this->info("Verificando ciclo de avaliação para o ano: {$year}");

            // 2. VERIFICAÇÃO CRUCIAL: Checa se já existem avaliações para este ano.
            $avaliacoesJaExistem = Evaluation::whereHas('form', function ($query) use ($year) {
                $query->where('year', $year);
            })->exists();

            if ($avaliacoesJaExistem) {
                $this->warn("As avaliações para o ano {$year} já foram geradas anteriormente (provavelmente de forma manual). Nenhuma ação necessária.");
                Log::warning("Tarefa automática pulada para o ano {$year}: avaliações já existem.");
                continue; // Pula para o próximo ano, se houver
            }

            // 3. Se não existem e a data é hoje, dispara o Job.
            $this->info("Nenhuma avaliação encontrada para {$year}. Disparando o Job de geração...");
            Log::info("Disparando GenerateEvaluationsJob para o ano {$year}...");

            try {
                GenerateEvaluationsJob::dispatch($year);
                $this->info("Job para o ano {$year} disparado com sucesso para processamento em fila.");
                Log::info("GenerateEvaluationsJob para o ano {$year} adicionado à fila com sucesso.");
            } catch (\Exception $e) {
                $this->error("Falha ao disparar o Job para o ano {$year}: " . $e->getMessage());
                Log::error("Falha na tarefa agendada ao tentar disparar o Job para o ano {$year}:", ['exception' => $e]);
            }
        }

        $this->info('Verificação concluída.');
        Log::info('Tarefa agendada evaluations:generate-auto finalizada.');
    }
}