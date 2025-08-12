<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Form;
use App\Models\Pdi; // Importe o model Pdi
use App\Jobs\GeneratePdiJob; // Importe o Job do PDI
use Carbon\Carbon;

class AutoGeneratePdis extends Command
{
    /**
     * O nome e a assinatura do comando do console.
     *
     * @var string
     */
    protected $signature = 'pdi:generate-auto';

    /**
     * A descrição do comando do console.
     *
     * @var string
     */
    protected $description = 'Verifica se os PDIs de um período já foram gerados. Se não foram, e a data de início é hoje, dispara o Job de geração.';

    /**
     * Executa o comando do console.
     */
    public function handle(): void
    {
        $this->info('Iniciando verificação para geração automática de PDIs...');

        $today = Carbon::today()->toDateString();
        // Tipos de formulários que identificam um ciclo de PDI
        $pdiFormTypes = ['pactuacao_servidor', 'pactuacao_comissionado', 'pactuacao_gestor'];

        // 1. Encontra todos os formulários de PDI que foram liberados e cujo prazo de início é hoje.
        $formsParaVerificar = Form::where('release', true)
            ->whereDate('term_first', $today)
            ->whereIn('type', $pdiFormTypes)
            ->get();

        if ($formsParaVerificar->isEmpty()) {
            $this->info('Nenhum período de PDI programado para iniciar hoje.');
            return;
        }

        // Agrupa por ano para tratar cada ciclo de PDI separadamente
        $anosParaVerificar = $formsParaVerificar->pluck('year')->unique();

        foreach ($anosParaVerificar as $year) {
            $this->info("Verificando ciclo de PDI para o ano: {$year}");

            // 2. VERIFICAÇÃO CRUCIAL: Checa se já existem PDIs para este ano.
            $pdisJaExistem = Pdi::where('year', $year)->exists();

            if ($pdisJaExistem) {
                $this->warn("Os PDIs para o ano {$year} já foram gerados anteriormente (provavelmente de forma manual). Nenhuma ação necessária.");
                continue; // Pula para o próximo ano, se houver
            }

            // 3. Se não existem e a data é hoje, dispara o Job.
            $this->info("Nenhum PDI encontrado para {$year}. Disparando o Job de geração...");

            try {
                GeneratePdiJob::dispatch($year);
                $this->info("Job de PDI para o ano {$year} disparado com sucesso para processamento em fila.");
            } catch (\Exception $e) {
                $this->error("Falha ao disparar o Job de PDI para o ano {$year}: " . $e->getMessage());
            }
        }

        $this->info('Verificação de PDIs concluída.');
    }
}