<?php
// File: app/Http/Controllers/DashboardController.php
// Descrição: Controller Laravel para servir a página do Dashboard via Inertia.

namespace App\Http\Controllers;
use App\Models\Form;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Função de ajuda para buscar o prazo de um grupo específico (se estiver liberado).
     *
     * @param string $groupName 'avaliacao' ou 'pdi'
     * @return \App\Models\Form|null
     */
    private function getGroupDeadline(string $groupName): ?Form
    {
        $currentYear = date('Y');
        $formTypes = [];

        if ($groupName === 'avaliacao') {
            $formTypes = ['autoavaliacao', 'servidor', 'chefia'];
        } elseif ($groupName === 'pdi') {
            $formTypes = ['pactuacao'];
        }

        // Busca o primeiro formulário do grupo/ano que esteja liberado (release = true)
        return Form::where('year', $currentYear)
            ->whereIn('type', $formTypes)
            ->where('release', true)
            ->select('term_first', 'term_end')
            ->first();
    }

    /**
     * Exibe a página principal do dashboard.
     *
     * @return \Inertia\Response
     */
    public function index(): Response
    {
        // Busca os prazos para ambos os grupos para o dashboard principal
        $prazoAvaliacao = $this->getGroupDeadline('avaliacao');
        $prazoPdi = $this->getGroupDeadline('pdi');

        return Inertia::render('Dashboard/Index', [
            'prazoAvaliacao' => $prazoAvaliacao,
            'prazoPdi' => $prazoPdi,
            // Outros dados para o Index, se houver...
        ]);
    }

    public function evaluation(): Response
    {
        // Busca o prazo apenas para o grupo de avaliação
        $prazo = $this->getGroupDeadline('avaliacao');

        return Inertia::render('Dashboard/Evaluation', [
            'prazo' => $prazo,
        ]);
    }

    public function pdi(): Response
    {
        // Busca o prazo apenas para o grupo de PDI
        $prazoPdi = $this->getGroupDeadline('pdi');

        return Inertia::render('Dashboard/PDI', [
            'prazoPdi' => $prazoPdi,
        ]);
    }

    public function calendar(): Response
    {
        // Busca todos os formulários que possuem um prazo definido
        $prazos = Form::whereNotNull('term_first')
            ->whereNotNull('term_end')
            ->select('year', 'type', 'term_first', 'term_end')
            ->get()
            // Agrupa primeiro por ano, depois por 'avaliacao' ou 'pdi'
            ->groupBy([
                'year',
                function ($item) {
                    return in_array($item->type, ['autoavaliacao', 'servidor', 'chefia']) ? 'avaliacao' : 'pdi';
                }
            ])
            // Mapeia os grupos para criar um evento simplificado
            ->map(function ($yearGroup) {
                return $yearGroup->map(function ($group, $groupName) {
                    $firstForm = $group->first(); // Pega o primeiro formulário do grupo como representante
                    return [
                        'start' => $firstForm->term_first->toDateString(), // Formato AAAA-MM-DD
                        'end' => $firstForm->term_end->toDateString(),     // Formato AAAA-MM-DD
                        'title' => 'Período ' . ucfirst($groupName) . ' ' . $firstForm->year,
                        'group' => $groupName, // 'avaliacao' ou 'pdi'
                    ];
                });
            })
            ->flatten(1) // Transforma a coleção de grupos em uma lista única
            ->values(); // Garante que seja um array simples

        // Passa a lista de eventos de prazo para o componente Vue
        return Inertia::render('Dashboard/Calendar', [
            'deadlineEvents' => $prazos,
        ]);
    }

    public function reports(): Response
    {
        // Você pode passar dados do backend para o frontend aqui
        $dashboardStats = [
            'completedAssessments' => 12,
            'pendingAssessments' => 3,
            'overallProgress' => '85%',
            'nextDeadline' => '25/06/2024', // Exemplo de dado
        ];

        return Inertia::render('Dashboard/Reports', [
            'stats' => $dashboardStats, // Esses dados estarão disponíveis como props no componente Dashboard/Index.vue
            // Outros dados necessários para a página
        ]);
    }

    public function configs()
    {
        
        $forms = Form::with('groupQuestions.questions')->get()->keyBy(function ($form) {
            return $form->year . '_' . $form->type;
        });

        // Lógica para buscar os anos únicos
        $existingYears = Form::select('year')->distinct()->pluck('year');

        // Passa os dados no formato correto para a view
        return Inertia::render('Dashboard/Configs', [
            'forms' => $forms,
            'existingYears' => $existingYears,
        ]);
    }
}