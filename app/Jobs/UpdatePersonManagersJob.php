<?php

namespace App\Jobs;

use App\Models\OrganizationalUnit;
use App\Models\Person;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdatePersonManagersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private array $estruturaChefias = [];
    private array $debugChefias = [];

    public function __construct() {}

    public function handle()
    {
        // Busca o PREFEITO MUNICIPAL (code 123) para ser chefe de todas as secretarias
        $prefeito = Person::whereHas('jobFunction', function ($q) {
            $q->where('code', 123)->where('name', 'PREFEITO MUNICIPAL');
        })
        ->where('functional_status', 'TRABALHANDO')
        ->first();

        $topLevelUnits = OrganizationalUnit::whereNull('parent_id')->get();

        foreach ($topLevelUnits as $unit) {
            $this->processUnitRecursive($unit, $prefeito);
        }

        // Loga a estrutura final de chefias
        $log = "\n[UpdatePersonManagersJob] Estrutura final de chefias:";
        foreach ($this->estruturaChefias as $unidade => $salas) {
            $log .= "\n- Unidade: {$unidade}";
            foreach ($salas as $nomeSala => $chefe) {
                $log .= "\n    - Sala: " . ($nomeSala !== '' ? $nomeSala : '[SEM SALA]') . ' -> Chefe: ' . ($chefe ?: '[SEM CHEFE]');
            }
        }

        // Loga o debug das atribuições
        $logDebug = "\n[UpdatePersonManagersJob] Debug das atribuições de chefia:";
        foreach ($this->debugChefias as $linha) {
            $logDebug .= "\n" . $linha;
        }
    }

    private function processUnitRecursive(OrganizationalUnit $unit, ?Person $chefePai = null)
    {
        $salas = Person::where('organizational_unit_id', $unit->id)
            ->pluck('sala')
            ->unique()
            ->filter()
            ->values();

        $nomeUnidade = $unit->name ?? ("Unidade ID " . $unit->id);
        $this->estruturaChefias[$nomeUnidade] = [];

        $chefePrincipal = null;
        $salaPrincipal = null;

        // 1. Procura sala com nome igual ao da unidade
        foreach ($salas as $sala) {
            if ($sala && trim($sala) === trim($unit->name)) {
                $chefePrincipal = $this->findUnitManager($unit, $sala);
                $salaPrincipal = $sala;
                break;
            }
        }

        // 2. Se não achou, procura a sala de menor código com chefe
        if (!$chefePrincipal && $salas->count() > 0) {
            $salasOrdenadas = $salas->sort();
            foreach ($salasOrdenadas as $sala) {
                $chefe = $this->findUnitManager($unit, $sala);
                if ($chefe) {
                    $chefePrincipal = $chefe;
                    $salaPrincipal = $sala;
                    break;
                }
            }
        }

        // AJUSTE: O chefe principal da unidade deve ter como chefe o chefePai, se houver!
        if ($chefePrincipal && $chefePai && $chefePrincipal->direct_manager_id != $chefePai->id) {
            $this->debugChefias[] = "{$chefePrincipal->name} (chefe principal da unidade: {$nomeUnidade}) agora responde para o chefe superior: {$chefePai->name}";
            $chefePrincipal->direct_manager_id = $chefePai->id;
            $chefePrincipal->save();
        }

        // 3. Percorre todas as salas para setar chefias (agora com o chefe principal certo)
        foreach ($salas as $sala) {
            $chefeAtual = $this->findUnitManager($unit, $sala);
            $membros = Person::where('organizational_unit_id', $unit->id)
                ->where('sala', $sala)
                ->get();

            if ($chefeAtual) {
                foreach ($membros as $membro) {
                    if ($membro->id !== $chefeAtual->id && $membro->direct_manager_id != $chefeAtual->id) {
                        $this->debugChefias[] = "{$membro->name} (sala: {$sala}, unidade: {$nomeUnidade}) agora responde para o chefe local: {$chefeAtual->name}";
                        $membro->direct_manager_id = $chefeAtual->id;
                        $membro->save();
                    }
                }
                $this->estruturaChefias[$nomeUnidade][$sala] = $chefeAtual->name;
            } elseif ($chefePrincipal) {
                foreach ($membros as $membro) {
                    if ($membro->direct_manager_id != $chefePrincipal->id) {
                        $this->debugChefias[] = "{$membro->name} (sala: {$sala}, unidade: {$nomeUnidade}) herdou chefe do ramo: {$chefePrincipal->name}";
                        $membro->direct_manager_id = $chefePrincipal->id;
                        $membro->save();
                    }
                }
                $this->estruturaChefias[$nomeUnidade][$sala] = $chefePrincipal->name;
            } elseif ($chefePai) {
                foreach ($membros as $membro) {
                    if ($membro->direct_manager_id != $chefePai->id) {
                        $this->debugChefias[] = "{$membro->name} (sala: {$sala}, unidade: {$nomeUnidade}) herdou chefe do nível superior: {$chefePai->name}";
                        $membro->direct_manager_id = $chefePai->id;
                        $membro->save();
                    }
                }
                $this->estruturaChefias[$nomeUnidade][$sala] = $chefePai->name;
            } else {
                foreach ($membros as $membro) {
                    $this->debugChefias[] = "{$membro->name} (sala: {$sala}, unidade: {$nomeUnidade}) ficou sem chefe definido.";
                }
                $this->estruturaChefias[$nomeUnidade][$sala] = null;
            }
        }

        // Pessoas sem sala
        $chefeSemSala = $this->findUnitManager($unit, null);
        $membrosSemSala = Person::where('organizational_unit_id', $unit->id)
            ->whereNull('sala')
            ->get();
        if ($membrosSemSala->count() > 0) {
            if ($chefeSemSala) {
                foreach ($membrosSemSala as $membro) {
                    if ($membro->id !== $chefeSemSala->id && $membro->direct_manager_id != $chefeSemSala->id) {
                        $this->debugChefias[] = "{$membro->name} (SEM SALA, unidade: {$nomeUnidade}) agora responde para o chefe local (sem sala): {$chefeSemSala->name}";
                        $membro->direct_manager_id = $chefeSemSala->id;
                        $membro->save();
                    }
                }
                $this->estruturaChefias[$nomeUnidade]['[SEM SALA]'] = $chefeSemSala->name;
            } elseif ($chefePrincipal) {
                foreach ($membrosSemSala as $membro) {
                    if ($membro->direct_manager_id != $chefePrincipal->id) {
                        $this->debugChefias[] = "{$membro->name} (SEM SALA, unidade: {$nomeUnidade}) herdou chefe do ramo: {$chefePrincipal->name}";
                        $membro->direct_manager_id = $chefePrincipal->id;
                        $membro->save();
                    }
                }
                $this->estruturaChefias[$nomeUnidade]['[SEM SALA]'] = $chefePrincipal->name;
            } elseif ($chefePai) {
                foreach ($membrosSemSala as $membro) {
                    if ($membro->direct_manager_id != $chefePai->id) {
                        $this->debugChefias[] = "{$membro->name} (SEM SALA, unidade: {$nomeUnidade}) herdou chefe do nível superior: {$chefePai->name}";
                        $membro->direct_manager_id = $chefePai->id;
                        $membro->save();
                    }
                }
                $this->estruturaChefias[$nomeUnidade]['[SEM SALA]'] = $chefePai->name;
            } else {
                foreach ($membrosSemSala as $membro) {
                    $this->debugChefias[] = "{$membro->name} (SEM SALA, unidade: {$nomeUnidade}) ficou sem chefe definido.";
                }
                $this->estruturaChefias[$nomeUnidade]['[SEM SALA]'] = null;
            }
        }

        // Recursão para todas as unidades filhas — chega até o último nível
        foreach ($unit->children as $childUnit) {
            $this->processUnitRecursive($childUnit, $chefePrincipal ?: $chefePai);
        }
    }

    // 1º busca SECRETARIO MUNICIPAL code 380 (prioritário), se não, chefe tradicional
    private function findUnitManager(OrganizationalUnit $unit, $sala): ?Person
    {
        // SECRETARIO MUNICIPAL (code 380) é prioritário!
        $secretario = Person::where('organizational_unit_id', $unit->id)
            ->where('functional_status', 'TRABALHANDO')
            ->where('sala', $sala)
            ->whereHas('jobFunction', function ($q) {
                $q->where('code', 380)
                  ->where('name', 'SECRETARIO MUNICIPAL');
            })
            ->first();

        if ($secretario) {
            return $secretario;
        }

        // Se não há SECRETARIO MUNICIPAL, busca chefe tradicional
        return Person::where('organizational_unit_id', $unit->id)
            ->where('functional_status', 'TRABALHANDO')
            ->where('sala', $sala)
            ->whereHas('jobFunction', function ($q) {
                $q->where('is_manager', '1');
            })
            ->first();
    }
}
