<?php

namespace App\Jobs;

use App\Models\Form;
use App\Models\Pdi;
use App\Models\PdiRequest;
use App\Models\Person;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GeneratePdiJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $year;

    public function __construct(string $year)
    {
        $this->year = $year;
    }

    public function handle(): void
    {
        Log::info("--- INICIANDO JOB: Geração de PDIs para o ano: {$this->year} ---");

        DB::beginTransaction();
        try {
            // Ajuste o 'type' para corresponder ao seu formulário de PDI em Configs.vue
            $pdiForm = Form::where('type', 'pactuacao_servidor')
                           ->where('year', $this->year)
                           ->firstOrFail();

            // Pega todos os servidores elegíveis que possuem um gestor direto
            $peopleWithManagers = Person::eligibleForEvaluation()
                                        ->whereNotNull('direct_manager_id')
                                        ->get();

            Log::info("Encontrados {$peopleWithManagers->count()} servidores com gestores diretos.");

            foreach ($peopleWithManagers as $person) {
                // 1. Cria ou encontra o registro principal do PDI
                $pdi = Pdi::firstOrCreate(
                    [
                        'person_id' => $person->id,
                        'year' => $this->year,
                    ],
                    [
                        'form_id' => $pdiForm->id,
                    ]
                );

                // 2. Cria a solicitação de fluxo
                $request = PdiRequest::firstOrCreate(
                    ['pdi_id' => $pdi->id],
                    [
                        'person_id' => $person->id,
                        'manager_id' => $person->direct_manager_id,
                        'status' => 'pending_manager_fill', // Status inicial
                    ]
                );

                if ($request->wasRecentlyCreated) {
                    Log::info("PDI Request criado para: {$person->name} (Gestor: {$person->directManager->name})");
                }
            }

            DB::commit();
            Log::info("--- SUCESSO: Geração de PDIs para o ano {$this->year} concluída. ---");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("FALHA CRÍTICA no GeneratePdiJob: " . $e->getMessage());
        }
    }
}