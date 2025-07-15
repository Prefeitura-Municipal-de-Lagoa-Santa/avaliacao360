<?php

namespace App\Jobs;

use App\Models\Evaluation;
use App\Models\EvaluationRequest;
use App\Models\Form;
use App\Models\Person;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateEvaluationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $year;
    protected Form $chefiaForm;
    protected Form $servidorForm;
    protected Form $gestorForm; 
    protected ?Form $comissionadoForm = null;
    protected int $chefiaCreatedCount = 0;
    protected int $servidorCreatedCount = 0;
    protected int $autoCreatedCount = 0; 

    public function __construct(string $year)
    {
        $this->year = $year;
    }

    public function handle(): void
    {
        Log::info("======================================================================");
        Log::info("INICIANDO JOB: Geração de avaliações para o ano: {$this->year}");
        Log::info("======================================================================");

        DB::beginTransaction();
        try {
            $this->servidorForm     = Form::where('type', 'servidor')->where('year', '>=', $this->year)->firstOrFail();
            $this->gestorForm       = Form::where('type', 'gestor')->where('year', '>=', $this->year)->firstOrFail();
            $this->chefiaForm       = Form::where('type', 'chefia')->where('year', '>=', $this->year)->firstOrFail();
            $this->comissionadoForm = Form::where('type', 'comissionado')->where('year', '>=', $this->year)->first();

            Log::info("[Formulário Servidor] Encontrado: ID {$this->servidorForm->id}");
            Log::info("[Formulário Gestor] Encontrado: ID {$this->gestorForm->id}");
            Log::info("[Formulário Chefia] Encontrado: ID {$this->chefiaForm->id}");
            Log::info("[Formulário Comissionado] Encontrado: ID " . ($this->comissionadoForm ? $this->comissionadoForm->id : 'NÃO ENCONTRADO'));

            // ======= APAGAR AVALIAÇÕES DO MESMO ANO OLHANDO O FORM =======
            $formsIds = Form::where('year', $this->year)->pluck('id');
            $evaluationIds = Evaluation::whereIn('form_id', $formsIds)->pluck('id');
            if ($evaluationIds->count()) {
                EvaluationRequest::whereIn('evaluation_id', $evaluationIds)->delete();
                Evaluation::whereIn('id', $evaluationIds)->delete();
                Log::info("Todas as avaliações e requests do ano {$this->year} foram removidas antes da nova geração.");
            }
            // =============================================================

            $this->generateSelfEvaluations();
            $this->generateManagerEvaluations();
            $this->generateManagerSelfIfNoSubordinates();

            Log::info("[Autoavaliação] Novas autoavaliações criadas: {$this->autoCreatedCount}");
            Log::info("[Chefia] Novas avaliações de chefia (de cima para baixo) criadas: {$this->chefiaCreatedCount}");
            Log::info("[Gestor] Novas avaliações de gestor (de baixo para cima) criadas: {$this->servidorCreatedCount}");

            DB::commit();
            Log::info("SUCESSO: Geração de avaliações para o ano {$this->year} concluída.");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("FALHA CRÍTICA no Job: " . $e->getMessage() . " na linha " . $e->getLine());
        }
        Log::info("======================================================================");
    }

    private function generateSelfEvaluations(): void
    {
        Log::info("--- Etapa de Autoavaliações ---");
        
        $people = Person::eligibleForEvaluation()->get();
        Log::info("Pessoas elegíveis para autoavaliação encontradas: {$people->count()}");

        foreach ($people as $person) {
            $isManager = !is_null($person->current_function);

            if ($isManager) {
                $evaluation = Evaluation::firstOrCreate(
                    ['form_id' => $this->gestorForm->id, 'evaluated_person_id' => $person->id, 'type' => 'autoavaliaçãoGestor']
                );
            } else {
                $evaluation = Evaluation::firstOrCreate(
                    ['form_id' => $this->servidorForm->id, 'evaluated_person_id' => $person->id, 'type' => 'autoavaliação']
                );
            }

            $request = EvaluationRequest::firstOrCreate(
                ['evaluation_id' => $evaluation->id, 'requested_person_id' => $person->id],
                ['requester_person_id' => $person->id, 'status' => 'pending']
            );
            
            if ($request->wasRecentlyCreated) {
                $this->autoCreatedCount++;
            }
        }
    }

    private function generateManagerEvaluations(): void
    {
        Log::info("--- Etapa de Avaliações de Chefia por direct_manager_id ---");
        
        $peopleWithManagers = Person::whereNotNull('direct_manager_id')->eligibleForEvaluation()->get();

        foreach ($peopleWithManagers as $person) {
            $manager = $person->directManager;
            if (!$manager) continue;

            // Lógica para definir o formulário e o tipo de avaliação
            $jobFunction = $person->jobFunction;
            $formToUse = $this->servidorForm;
            $evaluationType = 'servidor';

            if ($jobFunction && !$jobFunction->is_manager && $this->comissionadoForm) {
                $formToUse = $this->comissionadoForm;
                $evaluationType = 'comissionado';
            } elseif (!is_null($person->current_function)) {
                $formToUse = $this->gestorForm;
                $evaluationType = 'gestor';
            }

            // Avaliação do subordinado pelo chefe
            $evaluation = Evaluation::firstOrCreate(
                ['form_id' => $formToUse->id, 'evaluated_person_id' => $person->id, 'type' => $evaluationType]
            );
            $request = EvaluationRequest::firstOrCreate(
                ['evaluation_id' => $evaluation->id, 'requested_person_id' => $manager->id],
                ['requester_person_id' => $person->id, 'status' => 'pending']
            );
            if ($request->wasRecentlyCreated) {
                $this->chefiaCreatedCount++;
            }

            // Avaliação do chefe pelos subordinados (tipo chefia)
            $evaluationBoss = Evaluation::firstOrCreate(
                ['form_id' => $this->chefiaForm->id, 'evaluated_person_id' => $manager->id, 'type' => 'chefia']
            );
            $requestBoss = EvaluationRequest::firstOrCreate(
                ['evaluation_id' => $evaluationBoss->id, 'requested_person_id' => $person->id],
                ['requester_person_id' => $manager->id, 'status' => 'pending']
            );
            if ($requestBoss->wasRecentlyCreated) {
                $this->servidorCreatedCount++;
            }
        }
    }

    private function generateManagerSelfIfNoSubordinates(): void
    {
        $managers = Person::whereHas('jobFunction', function($q) {
            $q->where('is_manager', true);
        })->eligibleForEvaluation()->get();

        foreach ($managers as $manager) {
            $hasSubordinates = Person::where('direct_manager_id', $manager->id)
                ->eligibleForEvaluation()
                ->exists();

            if (!$hasSubordinates) {
                // Avaliação de gestor (autoavaliação, se ainda não criada)
                $evaluation = Evaluation::firstOrCreate(
                    ['form_id' => $this->gestorForm->id, 'evaluated_person_id' => $manager->id, 'type' => 'gestor']
                );
                $request = EvaluationRequest::firstOrCreate(
                    ['evaluation_id' => $evaluation->id, 'requested_person_id' => $manager->id],
                    ['requester_person_id' => $manager->id, 'status' => 'pending']
                );
                if ($request->wasRecentlyCreated) {
                    $this->autoCreatedCount++;
                    Log::info("[Gestor sem subordinados] Criada avaliação individual para {$manager->name} (ID {$manager->id})");
                }
            }
        }
    }
}
