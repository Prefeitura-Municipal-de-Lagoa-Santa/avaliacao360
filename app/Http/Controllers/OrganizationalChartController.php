<?php

namespace App\Http\Controllers;

use App\Models\OrganizationalUnit;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrganizationalChartController extends Controller
{
    /**
     * Exibe a página do organograma, separada por Secretaria.
     */
    public function index()
    {
        // 1. Busca todas as unidades para evitar múltiplas queries
        $allUnits = OrganizationalUnit::with('people:id,name,current_position,organizational_unit_id')->get();

        // 2. Identifica as unidades raiz (Secretarias)
        $rootUnits = $allUnits->whereNull('parent_id')->sortBy('name');

        // 3. Para cada Secretaria, constrói sua própria lista de nós para o organograma
        $charts = [];
        foreach ($rootUnits as $rootUnit) {
            $charts[] = [
                'id' => $rootUnit->id,
                'name' => $rootUnit->name,
                // Gera a lista plana de nós que o d3-org-chart precisa
                'chartData' => $this->getDescendantsAsFlatList($rootUnit, $allUnits)
            ];
        }

        // 4. Passa o array de organogramas para a view
        return Inertia::render('OrganizationalChart/Index', [
            'charts' => $charts,
        ]);
    }

    /**
     * Monta uma lista "plana" de nós (unidade e seus descendentes)
     * no formato que a biblioteca d3-org-chart espera.
     *
     * @param \App\Models\OrganizationalUnit $startNode O nó inicial (a Secretaria)
     * @param \Illuminate\Database\Eloquent\Collection $allUnits Todas as unidades do banco
     * @return array
     */
    private function getDescendantsAsFlatList($startNode, $allUnits) {
        $list = [];
        $this->buildFlatList($startNode, $allUnits, $list);
        return $list;
    }
    
    /**
     * Função auxiliar recursiva para construir a lista plana.
     */
    private function buildFlatList($currentNode, $allUnits, &$list) {
        // Adiciona o nó atual à lista
        $list[] = [
            'id' => $currentNode->id,
            'parentId' => $currentNode->parent_id,
            'label' => $currentNode->name,
            'type' => $currentNode->type,
            'people' => $currentNode->people->map(function ($person) {
                return ['name' => $person->name, 'position' => $person->current_position];
            })->toArray()
        ];

        // Encontra e processa os filhos deste nó
        $children = $allUnits->where('parent_id', $currentNode->id);
        foreach ($children as $child) {
            $this->buildFlatList($child, $allUnits, $list);
        }
    }
}
