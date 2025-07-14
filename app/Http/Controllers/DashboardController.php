<?php
// File: app/Http/Controllers/DashboardController.php
// Descrição: Controller Laravel para servir a página do Dashboard via Inertia.

namespace App\Http\Controllers;
use App\Models\Form;
use App\Models\Person;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\EvaluationRequest;

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
            $formTypes = ['servidor', 'gestor', 'chefia'];
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
    public function index()
    {
        if (!user_can('dashboard')) {
            return redirect()->route('evaluations');
        }

        // Busca os prazos
        $prazoAvaliacao = $this->getGroupDeadline('avaliacao');
        $prazoPdi = $this->getGroupDeadline('pdi');

        // --- INÍCIO DA ALTERAÇÃO ---

        // 2. Calcule os status da avaliação
        $completedAssessments = EvaluationRequest::where('status', 'completed')->count();
        $pendingAssessments = EvaluationRequest::where('status', 'pending')->count();
        $totalAssessments = $completedAssessments + $pendingAssessments;

        // 3. Calcule o progresso geral, tratando a divisão por zero
        $overallProgress = 0;
        if ($totalAssessments > 0) {
            $overallProgress = round(($completedAssessments / $totalAssessments) * 100);
        }

        // 4. Crie um array com os dados para o dashboard
        $dashboardStats = [
            'completedAssessments' => $completedAssessments,
            'pendingAssessments' => $pendingAssessments,
            'overallProgress' => $overallProgress . '%', // Formata como string de porcentagem
        ];

        // --- FIM DA ALTERAÇÃO ---

        return Inertia::render('Dashboard/Index', [
            'prazoAvaliacao' => $prazoAvaliacao,
            'prazoPdi' => $prazoPdi,
            'dashboardStats' => $dashboardStats, // 5. Passe os dados para a view
        ]);
    }

    public function evaluation()
    {
        $people = Person::where('cpf', Auth::user()->cpf)->first();
        $prazo = $this->getGroupDeadline('avaliacao');

        $estaNoPrazo = false;
        if ($prazo && $prazo->term_first && $prazo->term_end) {
            $hoje = now();
            $inicio = $prazo->term_first;
            $fim = $prazo->term_end;
            $estaNoPrazo = $hoje->between($inicio, $fim);
        }

        if (!$people) {
            return Inertia::render('Dashboard/Evaluation', [
                'prazo' => $prazo,
                'selfEvaluationVisible' => false,
                'bossEvaluationVisible' => false,
                'teamEvaluationVisible' => false,
            ]);
        }

        $selfEvaluationVisible = $estaNoPrazo && EvaluationRequest::where('requested_person_id', $people->id)
            ->where('status', 'pending')
            ->whereHas('evaluation', function ($query) {
                $query->where('type', 'servidor');
            })
            ->exists();

        $bossEvaluationVisible = $estaNoPrazo && EvaluationRequest::where('requested_person_id', $people->id)
            ->where('status', 'pending')
            ->whereHas('evaluation', function ($query) {
                $query->where('type', 'chefia');
            })
            ->exists();

        $teamEvaluationVisible = $estaNoPrazo && EvaluationRequest::where('requested_person_id', $people->id)
            ->where('status', 'pending')
            ->whereHas('evaluation', function ($query) {
                $query->where('type', 'equipe');
            })
            ->exists();

        return Inertia::render('Dashboard/Evaluation', [
            'prazo' => $prazo,
            'selfEvaluationVisible' => $selfEvaluationVisible,
            'bossEvaluationVisible' => $bossEvaluationVisible,
            'teamEvaluationVisible' => $teamEvaluationVisible,
        ]);
    }




    public function pdi()
    {

        if (!user_can('pdi')) {
            return redirect()->route('evaluations')->with('error', 'Você não tem permissão para acessar essa área.');
        }

        // Busca o prazo apenas para o grupo de PDI
        $prazoPdi = $this->getGroupDeadline('pdi');

        return Inertia::render('Dashboard/PDI', [
            'prazoPdi' => $prazoPdi,
        ]);
    }

    public function calendar()
    {
        if (!user_can('calendar')) {
            return redirect()->route('evaluations')->with('error', 'Você não tem permissão para acessar essa área.');
        }
        // Busca todos os formulários que possuem um prazo definido
        $prazos = Form::whereNotNull('term_first')
            ->whereNotNull('term_end')
            ->select('year', 'type', 'term_first', 'term_end')
            ->get()
            // Agrupa primeiro por ano, depois por 'avaliacao' ou 'pdi'
            ->groupBy([
                'year',
                function ($item) {
                    return in_array($item->type, ['servidor', 'gestor', 'chefia']) ? 'avaliacao' : 'pdi';
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

    public function reports()
    {
        if (!user_can('reports')) {
            return redirect()->route('evaluations')->with('error', 'Você não tem permissão para acessar essa área.');
        }

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

        if (!user_can('configs')) {
            $previous = url()->previous();
            return redirect(url()->previous())->with('error', 'Você não tem permissão para acessar essa área.');
        }

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