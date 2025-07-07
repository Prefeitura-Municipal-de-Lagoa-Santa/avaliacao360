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
    protected Form $gestorForm; 
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
            // ALTERADO: Carregando todos os formulários necessários
            $this->servidorForm = Form::where('type', 'servidor')->where('year', '>=', $this->year)->firstOrFail();
            $this->gestorForm = Form::where('type', 'gestor')->where('year', '>=', $this->year)->firstOrFail();
            // O formulário de chefia pode ser opcional dependendo da lógica
            $this->chefiaForm = Form::where('type', 'chefia')->where('year', '>=', $this->year)->firstOrFail();

            Log::info("[Formulário Servidor] Encontrado: ID {$this->servidorForm->id}");
            Log::info("[Formulário Gestor] Encontrado: ID {$this->gestorForm->id}");
            Log::info("[Formulário Chefia] Encontrado: ID {$this->chefiaForm->id}");
            
            // Etapa 1: Gerar todas as autoavaliações
            $this->generateSelfEvaluations();

            // Etapa 2: Gerar avaliações hierárquicas
            Log::info("--- Etapa de Avaliações Hierárquicas (Chefia e Gestor) ---");
            $topLevelUnits = OrganizationalUnit::whereNull('parent_id')->get();
            Log::info("Unidades de topo encontradas: {$topLevelUnits->count()}");
            
            foreach ($topLevelUnits as $unit) {
                $this->processOrganizationalUnit($unit, null);
            }
            
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
    // app/Jobs/GenerateEvaluationsJob.php

private function generateSelfEvaluations(): void
{
    Log::info("--- Etapa de Autoavaliações ---");
    
    $people = Person::eligibleForEvaluation()->get();
    Log::info("Pessoas elegíveis para autoavaliação encontradas: {$people->count()}");

    foreach ($people as $person) {
        // Verifica se a pessoa é um gestor
        $isManager = !is_null($person->current_function);

        if ($isManager) {
            // Lógica para GESTOR: autoavaliação do tipo 'autoavaliaçãoGestor' com form 'gestor'
            $evaluation = Evaluation::firstOrCreate(
                ['form_id' => $this->gestorForm->id, 'evaluated_person_id' => $person->id, 'type' => 'autoavaliaçãoGestor']
            );
        } else {
            // Lógica para SERVIDOR: autoavaliação do tipo 'autoavaliação' com form 'servidor'
            $evaluation = Evaluation::firstOrCreate(
                ['form_id' => $this->servidorForm->id, 'evaluated_person_id' => $person->id, 'type' => 'autoavaliação']
            );
        }

        // Cria a solicitação para a pessoa responder sua própria avaliação
        $request = EvaluationRequest::firstOrCreate(
            ['evaluation_id' => $evaluation->id, 'requested_person_id' => $person->id],
            ['requester_person_id' => $person->id, 'status' => 'pending']
        );
        
        if ($request->wasRecentlyCreated) {
            $this->autoCreatedCount++;
        }
    }
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
                // De cima para baixo: Chefe superior avalia os membros.
                Log::info("[Hierarquia]     -> Chefe superior ({$parentBoss->name}) irá avaliar os {$members->count()} membros desta unidade.");
                $this->createDownwardEvaluationRequests($members, $parentBoss);

                // De baixo para cima: Membros da unidade sem chefe avaliam o chefe superior.
                Log::info("[Hierarquia]     -> Membros desta unidade irão avaliar seu chefe superior ({$parentBoss->name}).");
                if($parentBoss->isEligibleForEvaluation()) {
                    $this->createServerToBossEvaluationRequests($parentBoss, $members);
                } else {
                    Log::info("[Hierarquia]     -> Chefe superior ({$parentBoss->name}) não é elegível para ser avaliado, pulando.");
                }

            } else {
                Log::info("[Hierarquia]     -> Unidade sem chefe e sem hierarquia superior. Ninguém para avaliar os membros.");
            }
        }

        foreach ($unit->children as $childUnit) {
            $this->processOrganizationalUnit($childUnit, $currentBoss ?? $parentBoss);
        }
    }

    // app/Jobs/GenerateEvaluationsJob.php

private function createDownwardEvaluationRequests(Collection $evaluatedPeople, Person $evaluator): void
{
    foreach ($evaluatedPeople as $evaluated) {
        // Verifica se o AVALIADO é um gestor
        $isEvaluatedAManager = !is_null($evaluated->current_function);
        
        // CORREÇÃO: O tipo e o formulário da avaliação dependem da função do AVALIADO
        if ($isEvaluatedAManager) {
            // Se o avaliado é um gestor, a avaliação é do tipo 'gestor' com formulário 'gestor'
            $formToUse = $this->gestorForm;
            $evaluationType = 'gestor';
        } else {
            // Se o avaliado é um servidor, a avaliação é do tipo 'servidor' com formulário 'servidor'
            $formToUse = $this->servidorForm;
            $evaluationType = 'servidor';
        }
        
        $evaluation = Evaluation::firstOrCreate(
            ['form_id' => $formToUse->id, 'evaluated_person_id' => $evaluated->id, 'type' => $evaluationType]
        );
        
        $request = EvaluationRequest::firstOrCreate(
            ['evaluation_id' => $evaluation->id, 'requested_person_id' => $evaluator->id],
            ['requester_person_id' => $evaluated->id, 'status' => 'pending']
        );
        
        if ($request->wasRecentlyCreated) {
            $this->chefiaCreatedCount++;
        }
    }
}
    // app/Jobs/GenerateEvaluationsJob.php

private function createServerToBossEvaluationRequests(Person $bossToEvaluate, Collection $evaluatingMembers): void
{
    // CORREÇÃO: Todos os membros da equipe (servidores e gestores) avaliam o chefe
    // usando o formulário e o tipo 'chefia'.
    $evaluation = Evaluation::firstOrCreate([
        'form_id' => $this->chefiaForm->id, // Formulário de chefia
        'evaluated_person_id' => $bossToEvaluate->id,
        'type' => 'chefia' // Tipo de avaliação é chefia
    ]);

    // Cria uma solicitação para cada membro da equipe
    foreach ($evaluatingMembers as $member) {
        $request = EvaluationRequest::firstOrCreate(
            [
                'evaluation_id' => $evaluation->id,
                'requested_person_id' => $member->id,
            ],
            [
                'requester_person_id' => $bossToEvaluate->id,
                'status' => 'pending'
            ]
        );
        if ($request->wasRecentlyCreated) {
            $this->servidorCreatedCount++;
        }
    }
}
}