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
        // Você pode passar dados do backend para o frontend aqui
        $dashboardStats = [
            'completedAssessments' => 12,
            'pendingAssessments' => 3,
            'overallProgress' => '85%',
            'nextDeadline' => '25/06/2024', // Exemplo de dado
        ];

        return Inertia::render('Dashboard/Calendar', [
            'stats' => $dashboardStats, // Esses dados estarão disponíveis como props no componente Dashboard/Index.vue
            // Outros dados necessários para a página
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
        // A chave aqui é o ->keyBy(...). Ele organiza os formulários
        // em um formato que o Vue consegue ler rapidamente.
        $forms = Form::with('questions')->get()->keyBy(function ($form) {
            return $form->year . '_' . $form->type;
        });

        // Lógica para buscar os anos únicos que já implementamos
        $existingYears = Form::select('year')->distinct()->pluck('year');

        // Passa os dados no formato correto para a view
        return Inertia::render('Dashboard/Configs', [
            'forms' => $forms,
            'existingYears' => $existingYears,
        ]);
    }
}