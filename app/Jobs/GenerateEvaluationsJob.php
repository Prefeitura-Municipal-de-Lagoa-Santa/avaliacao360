<?php

namespace App\Jobs;

use App\Models\Evaluation;
use App\Models\EvaluationRequest;
use App\Models\Form;
use App\Models\OrganizationalUnit;
use App\Models\Person;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateEvaluationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $year;
    protected Form $chefiaForm;
    protected Form $servidorForm;
    protected int $chefiaCreatedCount = 0;
    protected int $servidorCreatedCount = 0;

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
            $this->generateSelfEvaluations($this->year);

            $this->chefiaForm = Form::where('type', 'chefia')->where('year', '>=', $this->year)->firstOrFail();
            $this->servidorForm = Form::where('type', 'servidor')->where('year', '>=', $this->year)->firstOrFail();
            $topLevelUnits = OrganizationalUnit::whereNull('parent_id')->get();

            Log::info("--- Etapa de Avaliações de Chefia e Servidores (Hierárquica) ---");
            Log::info("[Chefia] Formulário encontrado: ID {$this->chefiaForm->id}");
            Log::info("[Servidor] Formulário encontrado: ID {$this->servidorForm->id}");
            Log::info("Unidades de topo encontradas: {$topLevelUnits->count()}");

            foreach ($topLevelUnits as $unit) {
                $this->processOrganizationalUnit($unit, null);
            }
            Log::info("[Chefia] Novas avaliações de chefia criadas: {$this->chefiaCreatedCount}");
            Log::info("[Servidor] Novas avaliações de servidores criadas: {$this->servidorCreatedCount}");


            DB::commit();
            Log::info("SUCESSO: Geração de avaliações para o ano {$this->year} concluída.");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("FALHA CRÍTICA no Job: " . $e->getMessage() . " na linha " . $e->getLine());
        }
        Log::info("======================================================================");
    }

    private function generateSelfEvaluations(string $year): void
    {
        Log::info("--- Etapa de Autoavaliações ---");
        $form = Form::where('type', 'autoavaliacao')->where('year', '>=', $year)->firstOrFail();
        Log::info("[Autoavaliação] Formulário encontrado: ID {$form->id}");

        $people = Person::eligibleForEvaluation()->get();
        Log::info("[Autoavaliação] Pessoas elegíveis encontradas: {$people->count()}");

        $createdCount = 0;
        foreach ($people as $person) {
            $evaluation = Evaluation::firstOrCreate(
                ['form_id' => $form->id, 'evaluated_person_id' => $person->id, 'type' => 'autoavaliacao']
            );
            $request = EvaluationRequest::firstOrCreate(
                ['evaluation_id' => $evaluation->id, 'requested_person_id' => $person->id],
                ['requester_person_id' => $person->id, 'status' => 'approved']
            );
            if ($request->wasRecentlyCreated) $createdCount++;
        }
        Log::info("[Autoavaliação] Novas autoavaliações criadas: {$createdCount}");
    }

    private function processOrganizationalUnit(OrganizationalUnit $unit, ?Person $parentBoss): void
    {
        Log::info("[Hierarquia] Processando unidade: {$unit->name} (ID: {$unit->id})");

        $currentBoss = Person::where('organizational_unit_id', $unit->id)
            ->where('functional_status', 'TRABALHANDO')
            ->where(function ($query) {
                $query->whereNotNull('current_function')
                      ->orWhere('current_position', '380-SECRETARIO MUNICIPAL');
            })
            ->first();
        
        $membersQuery = Person::where('organizational_unit_id', $unit->id)->eligibleForEvaluation(); 

        if ($currentBoss) {
            $membersQuery->where('id', '!=', $currentBoss->id);
        }
        $members = $membersQuery->get();

        if ($currentBoss) {
            Log::info("[Hierarquia]   -> Chefe encontrado: {$currentBoss->name} (ID: {$currentBoss->id})");
            $this->createDownwardEvaluationRequests($members, $currentBoss);
            
            if ($parentBoss) {
                Log::info("[Hierarquia]     -> Chefe superior ({$parentBoss->name}) irá avaliar {$currentBoss->name}.");
                if($currentBoss->isEligibleForEvaluation()) {
                    $this->createDownwardEvaluationRequests(collect([$currentBoss]), $parentBoss);
                } else {
                     Log::info("[Hierarquia]     -> Chefe ({$currentBoss->name}) não é elegível para avaliação, pulando.");
                }

                Log::info("[Hierarquia]     -> Chefe ({$currentBoss->name}) irá avaliar seu chefe superior ({$parentBoss->name}).");
                if ($parentBoss->isEligibleForEvaluation()) {
                    $this->createServerToBossEvaluationRequests($parentBoss, collect([$currentBoss]));
                } else {
                    Log::info("[Hierarquia]     -> Chefe superior ({$parentBoss->name}) não é elegível para ser avaliado, pulando.");
                }

            } else {
                Log::info("[Hierarquia]     -> {$currentBoss->name} está no topo da hierarquia, não tem avaliador superior.");
            }

            if ($currentBoss->isEligibleForEvaluation()) {
                Log::info("[Hierarquia]   -> Servidores irão avaliar o chefe {$currentBoss->name}.");
                $this->createServerToBossEvaluationRequests($currentBoss, $members);
            } else {
                Log::info("[Hierarquia]   -> Chefe {$currentBoss->name} não é elegível para ser avaliado, pulando avaliação de servidores.");
            }

        } else {
            Log::info("[Hierarquia]   -> Nenhum chefe encontrado nesta unidade.");
            if ($parentBoss) {
                // De cima para baixo: Chefe superior avalia os membros. (Lógica existente - CORRETA)
                Log::info("[Hierarquia]     -> Chefe superior ({$parentBoss->name}) irá avaliar os {$members->count()} membros desta unidade.");
                $this->createDownwardEvaluationRequests($members, $parentBoss);

                // ===== INÍCIO DA NOVA LÓGICA =====
                // De baixo para cima: Membros da unidade sem chefe avaliam o chefe superior.
                Log::info("[Hierarquia]     -> Membros desta unidade irão avaliar seu chefe superior ({$parentBoss->name}).");
                if($parentBoss->isEligibleForEvaluation()) {
                    $this->createServerToBossEvaluationRequests($parentBoss, $members);
                } else {
                    Log::info("[Hierarquia]     -> Chefe superior ({$parentBoss->name}) não é elegível para ser avaliado, pulando.");
                }
                // ===== FIM DA NOVA LÓGICA =====

            } else {
                Log::info("[Hierarquia]     -> Unidade sem chefe e sem hierarquia superior. Ninguém para avaliar os membros.");
            }
        }

        foreach ($unit->children as $childUnit) {
            $this->processOrganizationalUnit($childUnit, $currentBoss ?? $parentBoss);
        }
    }

    private function createDownwardEvaluationRequests(Collection $evaluatedPeople, Person $evaluator): void
    {
        foreach ($evaluatedPeople as $evaluated) {
            $evaluation = Evaluation::firstOrCreate(
                ['form_id' => $this->chefiaForm->id, 'evaluated_person_id' => $evaluated->id, 'type' => 'chefia']
            );
            $request = EvaluationRequest::firstOrCreate(
                ['evaluation_id' => $evaluation->id, 'requested_person_id' => $evaluator->id],
                ['requester_person_id' => $evaluated->id, 'status' => 'approved']
            );
            if ($request->wasRecentlyCreated) {
                $this->chefiaCreatedCount++;
            }
        }
    }
    
    private function createServerToBossEvaluationRequests(Person $bossToEvaluate, Collection $evaluatingMembers): void
    {
        $evaluation = Evaluation::firstOrCreate(
            [
                'form_id' => $this->servidorForm->id,
                'evaluated_person_id' => $bossToEvaluate->id,
                'type' => 'servidor'
            ]
        );

        foreach ($evaluatingMembers as $member) {
            $request = EvaluationRequest::firstOrCreate(
                [
                    'evaluation_id' => $evaluation->id,
                    'requested_person_id' => $member->id,
                ],
                [
                    'requester_person_id' => $bossToEvaluate->id,
                    'status' => 'approved'
                ]
            );
            if ($request->wasRecentlyCreated) {
                $this->servidorCreatedCount++;
            }
        }
    }
}
