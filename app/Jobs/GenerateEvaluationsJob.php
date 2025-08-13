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

        DB::beginTransaction();
        try {
            $this->servidorForm     = Form::where('type', 'servidor')->where('year', '>=', $this->year)->firstOrFail();
            $this->gestorForm       = Form::where('type', 'gestor')->where('year', '>=', $this->year)->firstOrFail();
            $this->chefiaForm       = Form::where('type', 'chefia')->where('year', '>=', $this->year)->firstOrFail();
            $this->comissionadoForm = Form::where('type', 'comissionado')->where('year', '>=', $this->year)->first();

            // ======= APAGAR AVALIAÇÕES DO MESMO ANO OLHANDO O FORM =======
            $formsIds = Form::where('year', $this->year)->pluck('id');
            $evaluationIds = Evaluation::whereIn('form_id', $formsIds)->pluck('id');
            if ($evaluationIds->count()) {
                EvaluationRequest::whereIn('evaluation_id', $evaluationIds)->delete();
                Evaluation::whereIn('id', $evaluationIds)->delete();
            }
            // =============================================================

            $this->generateSelfEvaluations();
            $this->generateManagerEvaluations();
            $this->generateManagerSelfIfNoSubordinates();

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
        }
    }

    private function pessoasElegiveis()
    {
        // Retorna pessoas elegíveis para RECEBER avaliação (autoavaliação e avaliação por superiores)
        // Exclui: 8 - Concursado (mesmo com job_function_id)
        return Person::eligibleForEvaluation()
            ->where('bond_type', '!=', '8 - Concursado');
    }

    private function pessoasQuePodemAvaliar()
    {
        // Retorna pessoas que podem FAZER avaliações (incluindo 8 - Concursado com função)
        return Person::eligibleForEvaluation()
            ->where(function ($query) {
                $query->where('bond_type', '!=', '8 - Concursado')
                      ->orWhereNotNull('job_function_id');
            });
    }

    private function generateSelfEvaluations(): void
    {

        $people = $this->pessoasElegiveis()->get();

        foreach ($people as $person) {
            $jobFunction = $person->jobFunction;
            $isManager = $jobFunction && $jobFunction->is_manager;
            $isComissionado = $jobFunction && !$jobFunction->is_manager && $this->comissionadoForm;

            if ($isManager) {
                $evaluation = Evaluation::firstOrCreate(
                    ['form_id' => $this->gestorForm->id, 'evaluated_person_id' => $person->id, 'type' => 'autoavaliaçãoGestor']
                );
            } elseif ($isComissionado) {
                $evaluation = Evaluation::firstOrCreate(
                    ['form_id' => $this->comissionadoForm->id, 'evaluated_person_id' => $person->id, 'type' => 'autoavaliaçãoComissionado']
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
        // Pessoas que PODEM SER AVALIADAS (excluindo 8 - Concursado)
        $peopleWithManagers = $this->pessoasElegiveis()
            ->whereNotNull('direct_manager_id')
            ->get();

        foreach ($peopleWithManagers as $person) {
            $manager = $person->directManager;
            if (!$manager) continue;

            $jobFunction = $person->jobFunction;

            if ($jobFunction && $jobFunction->is_manager) {
                $formToUse = $this->gestorForm;
                $evaluationType = 'gestor';
            } elseif ($jobFunction && !$jobFunction->is_manager && $this->comissionadoForm) {
                $formToUse = $this->comissionadoForm;
                $evaluationType = 'comissionado';
            } else {
                $formToUse = $this->servidorForm;
                $evaluationType = 'servidor';
            }

            // Avaliação da pessoa pelo seu chefe
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
        }

        // Agora, criar avaliações de CHEFES por subordinados (incluindo 8 - Concursado que podem avaliar)
        // MAS só para chefes que PODEM SER AVALIADOS (excluindo 8 - Concursado)
        $peopleWhoCanEvaluate = $this->pessoasQuePodemAvaliar()
            ->whereNotNull('direct_manager_id')
            ->get();

        foreach ($peopleWhoCanEvaluate as $person) {
            $manager = $person->directManager;
            if (!$manager) continue;

            // IMPORTANTE: Só cria avaliação do chefe se ele PODE SER AVALIADO
            // (ou seja, não é 8 - Concursado)
            $managerCanBeEvaluated = $this->pessoasElegiveis()
                ->where('id', $manager->id)
                ->exists();

            if (!$managerCanBeEvaluated) {
                continue; // Pula se o manager é 8 - Concursado
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
        // Apenas gestores que PODEM SER AVALIADOS (excluindo 8 - Concursado)
        $managers = $this->pessoasElegiveis()
            ->whereHas('jobFunction', function ($q) {
                $q->where('is_manager', true);
            })
            ->get();

        foreach ($managers as $manager) {
            // Verifica subordinados que PODEM AVALIAR (incluindo 8 - Concursado com função)
            $hasSubordinates = $this->pessoasQuePodemAvaliar()
                ->where('direct_manager_id', $manager->id)
                ->exists();

            if (!$hasSubordinates) {
                $evaluation = Evaluation::firstOrCreate(
                    ['form_id' => $this->gestorForm->id, 'evaluated_person_id' => $manager->id, 'type' => 'gestor']
                );
                $request = EvaluationRequest::firstOrCreate(
                    ['evaluation_id' => $evaluation->id, 'requested_person_id' => $manager->id],
                    ['requester_person_id' => $manager->id, 'status' => 'pending']
                );
                if ($request->wasRecentlyCreated) {
                    $this->autoCreatedCount++;
                }
            }
        }
    }
}
