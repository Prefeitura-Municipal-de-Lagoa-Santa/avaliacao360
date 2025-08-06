<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\EvaluationRecourse;
use App\Models\Person;
use App\Models\User;

class DebugRecourseAssignment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'debug:recourse-assignment {cpf}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Debug recourse assignment for a specific user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $cpf = $this->argument('cpf');
        
        // Busca o usuário
        $user = User::where('cpf', $cpf)->first();
        if (!$user) {
            $this->error("Usuário com CPF {$cpf} não encontrado.");
            return;
        }
        
        $this->info("USUÁRIO ENCONTRADO:");
        $this->info("Nome: {$user->name}");
        $this->info("CPF: {$user->cpf}");
        
        // Busca a pessoa
        $person = Person::where('cpf', $cpf)->first();
        if (!$person) {
            $this->error("Pessoa com CPF {$cpf} não encontrada na tabela persons.");
            return;
        }
        
        $this->info("\nPESSOA ENCONTRADA:");
        $this->info("Nome: {$person->name}");
        $this->info("ID: {$person->id}");
        
        // Busca recursos onde a pessoa é responsável
        $resources = EvaluationRecourse::whereHas('responsiblePersons', function($q) use ($person) {
            $q->where('person_id', $person->id);
        })->with('responsiblePersons')->get();
        
        $this->info("\nRECURSOS ONDE É RESPONSÁVEL:");
        if ($resources->count() === 0) {
            $this->warn("Nenhum recurso encontrado onde esta pessoa é responsável.");
        } else {
            foreach ($resources as $resource) {
                $this->info("- ID: {$resource->id} | Status: {$resource->status}");
            }
        }
        
        // Lista todos os recursos e seus responsáveis
        $this->info("\nTODOS OS RECURSOS E RESPONSÁVEIS:");
        $allResources = EvaluationRecourse::with('responsiblePersons')->get();
        foreach ($allResources as $resource) {
            $this->info("Recurso ID: {$resource->id} | Status: {$resource->status}");
            if ($resource->responsiblePersons->count() > 0) {
                foreach ($resource->responsiblePersons as $resp) {
                    $this->info("  - Responsável: {$resp->name} (ID: {$resp->id})");
                }
            } else {
                $this->warn("  - Nenhum responsável atribuído");
            }
        }
        
        // Simula exatamente o que o controller faz
        $this->info("\nSIMULANDO CONTROLLER index() para Comissão:");
        
        // Simula user_can('recourse') = false (não é RH)
        $isRH = false;
        $this->info("isRH: " . ($isRH ? 'true' : 'false'));
        
        // Sem parâmetro status (como na chamada do dashboard da Comissão)
        $status = null;
        if (!$status && $isRH) {
            $status = 'aberto';
        }
        $this->info("Status filtro: " . ($status ?? 'null (todos os status)'));
        
        $query = \App\Models\EvaluationRecourse::with([
            'person',
            'evaluation.evaluation.form',
            'responsiblePersons',
        ]);

        // Aplica filtro de responsabilidade (não é RH)
        if (!$isRH) {
            $query->whereHas('responsiblePersons', function($q) use ($person) {
                $q->where('person_id', $person->id);
            });
        }

        // Aplica filtro de status se houver
        if ($status) {
            $query->where('status', $status);
        }

        $recourses = $query->get();
        
        $this->info("RESULTADO DA CONSULTA:");
        if ($recourses->count() === 0) {
            $this->warn("Nenhum recurso encontrado com os filtros aplicados.");
        } else {
            foreach ($recourses as $resource) {
                $this->info("- ID: {$resource->id} | Status: {$resource->status} | Pessoa: " . ($resource->person->name ?? 'N/A'));
            }
        }
    }
}
