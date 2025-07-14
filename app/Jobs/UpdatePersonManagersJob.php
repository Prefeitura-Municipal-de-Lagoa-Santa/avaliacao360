<?php

namespace App\Jobs;

use App\Models\OrganizationalUnit;
use App\Models\Person;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdatePersonManagersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Você pode adicionar filtros ou parâmetros se quiser rodar só para algumas pessoas, etc.
    public function __construct()
    {
    }

    public function handle()
    {
        Log::info('[UpdatePersonManagersJob] Iniciando atualização de chefias via organograma.');

        $topLevelUnits = OrganizationalUnit::whereNull('parent_id')->get();
        $totalUnidades = $topLevelUnits->count();

        Log::info("[UpdatePersonManagersJob] Unidades de topo encontradas: $totalUnidades");

        foreach ($topLevelUnits as $unit) {
            $this->processUnitRecursive($unit, null);
        }

        Log::info('[UpdatePersonManagersJob] Atualização de chefias finalizada.');
    }

    /**
     * Função recursiva que percorre a árvore organizacional,
     * define o chefe da unidade (apenas is_manager = true) e atualiza os membros.
     */
    private function processUnitRecursive(OrganizationalUnit $unit, ?Person $chefePai)
    {
        // Define o chefe da unidade (apenas quem tem is_manager = true)
        $chefeAtual = $this->findUnitManager($unit);

        // Todos os membros da unidade (menos o chefe)
        $membros = $this->findUnitMembers($unit, $chefeAtual);

        // Atualiza os membros da unidade para apontar o chefe correto
        if ($chefeAtual) {
            foreach ($membros as $membro) {
                if ($membro->direct_manager_id != $chefeAtual->id) {
                    $old = $membro->direct_manager_id;
                    $membro->direct_manager_id = $chefeAtual->id;
                    $membro->save();
                    Log::info("[UpdatePersonManagersJob] Atualizado manager de {$membro->name} (ID: {$membro->id}) de {$old} para {$chefeAtual->id}");
                }
            }
        } elseif ($chefePai) {
            // Se não há chefe na unidade, aponta para o chefe pai (hierarquia superior)
            foreach ($membros as $membro) {
                if ($membro->direct_manager_id != $chefePai->id) {
                    $old = $membro->direct_manager_id;
                    $membro->direct_manager_id = $chefePai->id;
                    $membro->save();
                    Log::info("[UpdatePersonManagersJob] Atualizado manager de {$membro->name} (ID: {$membro->id}) de {$old} para {$chefePai->id}");
                }
            }
        }

        // Recursão para as unidades filhas
        foreach ($unit->children as $childUnit) {
            $this->processUnitRecursive($childUnit, $chefeAtual ?? $chefePai);
        }
    }

    /**
     * Busca o chefe da unidade (apenas is_manager = true e status 'TRABALHANDO').
     */
    private function findUnitManager(OrganizationalUnit $unit): ?Person
    {
        return Person::where('organizational_unit_id', $unit->id)
            ->where('functional_status', 'TRABALHANDO')
            ->where('is_manager', true)
            ->first();
    }

    /**
     * Busca todos os membros da unidade, exceto o chefe (se houver).
     */
    private function findUnitMembers(OrganizationalUnit $unit, ?Person $chefeAtual)
    {
        return Person::where('organizational_unit_id', $unit->id)
            ->when($chefeAtual, function ($q) use ($chefeAtual) {
                $q->where('id', '!=', $chefeAtual->id);
            })
            ->get();
    }
}
