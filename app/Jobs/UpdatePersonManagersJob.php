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

    private array $estruturaChefias = [];

    public function __construct() {}

    public function handle()
    {
        $topLevelUnits = OrganizationalUnit::whereNull('parent_id')->get();

        foreach ($topLevelUnits as $unit) {
            $this->processUnitRecursive($unit, null);
        }

        // Loga a estrutura final de chefias
        $log = "\n[UpdatePersonManagersJob] Estrutura final de chefias:";
        foreach ($this->estruturaChefias as $unidade => $salas) {
            $log .= "\n- Unidade: {$unidade}";
            foreach ($salas as $nomeSala => $chefe) {
                $log .= "\n    - Sala: " . ($nomeSala !== '' ? $nomeSala : '[SEM SALA]') . ' -> Chefe: ' . ($chefe ?: '[SEM CHEFE]');
            }
        }
        Log::info($log);
    }

    private function processUnitRecursive(OrganizationalUnit $unit, ?Person $chefePai = null)
    {
        $salas = Person::where('organizational_unit_id', $unit->id)
            ->pluck('sala')
            ->unique()
            ->filter();

        $nomeUnidade = $unit->name ?? ("Unidade ID " . $unit->id);
        $this->estruturaChefias[$nomeUnidade] = [];

        // Variável que pode ser atualizada para cada sala:
        $chefePaiParaFilhos = $chefePai;

        foreach ($salas as $sala) {
            $chefeAtual = $this->findUnitManager($unit, $sala);
            $membros = Person::where('organizational_unit_id', $unit->id)
                ->where('sala', $sala)
                ->get();

            // Se sala == código da unidade E tem chefe, passa esse chefe para os filhos
            if ($unit->code == $sala && $chefeAtual) {
                $chefePaiParaFilhos = $chefeAtual;
            }

            if ($chefeAtual) {
                foreach ($membros as $membro) {
                    if ($membro->id !== $chefeAtual->id && $membro->direct_manager_id != $chefeAtual->id) {
                        $membro->direct_manager_id = $chefeAtual->id;
                        $membro->save();
                    }
                }
                $this->estruturaChefias[$nomeUnidade][$sala] = $chefeAtual->name;
            } elseif ($chefePai) {
                foreach ($membros as $membro) {
                    if ($membro->direct_manager_id != $chefePai->id) {
                        $membro->direct_manager_id = $chefePai->id;
                        $membro->save();
                    }
                }
                $this->estruturaChefias[$nomeUnidade][$sala] = $chefePai->name;
            } else {
                $this->estruturaChefias[$nomeUnidade][$sala] = null;
            }
        }

        // Pessoas sem sala
        $chefeAtual = $this->findUnitManager($unit, null);
        $membrosSemSala = Person::where('organizational_unit_id', $unit->id)
            ->whereNull('sala')
            ->get();
        if ($membrosSemSala->count() > 0) {
            if ($chefeAtual) {
                foreach ($membrosSemSala as $membro) {
                    if ($membro->id !== $chefeAtual->id && $membro->direct_manager_id != $chefeAtual->id) {
                        $membro->direct_manager_id = $chefeAtual->id;
                        $membro->save();
                    }
                }
                $this->estruturaChefias[$nomeUnidade]['[SEM SALA]'] = $chefeAtual->name;
            } elseif ($chefePai) {
                foreach ($membrosSemSala as $membro) {
                    if ($membro->direct_manager_id != $chefePai->id) {
                        $membro->direct_manager_id = $chefePai->id;
                        $membro->save();
                    }
                }
                $this->estruturaChefias[$nomeUnidade]['[SEM SALA]'] = $chefePai->name;
            } else {
                $this->estruturaChefias[$nomeUnidade]['[SEM SALA]'] = null;
            }
        }

        // Recursão para unidades filhas, passando o chefePai (pode ter sido atualizado)
        foreach ($unit->children as $childUnit) {
            $this->processUnitRecursive($childUnit, $chefePaiParaFilhos);
        }
    }

    private function findUnitManager(OrganizationalUnit $unit, $sala): ?Person
    {
        return Person::where('organizational_unit_id', $unit->id)
            ->where('functional_status', 'TRABALHANDO')
            ->where('sala', $sala)
            ->whereHas('jobFunction', function ($q) {
                $q->where('type', 'chefe');
            })
            ->first();
    }
}
