<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\Person;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\EvaluationRequest;
use App\Models\configs as Config; // Importar o model de configurações
use Carbon\Carbon; // Importar a classe Carbon

class DashboardController extends Controller
{
    /**
     * Função de ajuda para buscar o prazo de um grupo específico.
     */
    private function getGroupDeadline(string $groupName): ?Form
    {
        $currentYear = date('Y');
        $formTypes = [];

        if ($groupName === 'avaliacao') {
            $formTypes = ['servidor', 'gestor', 'chefia', 'comissionado'];
        } elseif ($groupName === 'pdi') {
            // ***** CORREÇÃO APLICADA AQUI *****
            $formTypes = ['pactuacao_servidor', 'pactuacao_comissionado', 'pactuacao_gestor'];
        }

        return Form::where('year', $currentYear)
            ->whereIn('type', $formTypes)
            ->where('release', true)
            ->select('term_first', 'term_end')
            ->first();
    }

    /**
     * Exibe a página principal do dashboard.
     */
    public function index()
    {
        if (!user_can('dashboard')) {
            return redirect()->route('evaluations');
        }

        $prazoAvaliacao = $this->getGroupDeadline('avaliacao');
        $prazoPdi = $this->getGroupDeadline('pdi');

        $completedAssessments = EvaluationRequest::where('status', 'completed')->count();
        $pendingAssessments = EvaluationRequest::where('status', 'pending')->count();
        $totalAssessments = $completedAssessments + $pendingAssessments;

        $overallProgress = ($totalAssessments > 0) ? round(($completedAssessments / $totalAssessments) * 100) : 0;

        $dashboardStats = [
            'completedAssessments' => $completedAssessments,
            'pendingAssessments' => $pendingAssessments,
            'overallProgress' => $overallProgress . '%',
        ];

        return Inertia::render('Dashboard/Index', [
            'prazoAvaliacao' => $prazoAvaliacao,
            'prazoPdi' => $prazoPdi,
            'dashboardStats' => $dashboardStats,
        ]);
    }
    
    // ... (os outros métodos como evaluation(), pdi(), etc., permanecem os mesmos) ...
    public function evaluation()
    {
        // dd(Auth::user()->cpf);
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
                'selfEvaluationCompleted' => false,
                'bossEvaluationCompleted' => false,
                'teamEvaluationCompleted' => false,
                // IDs para página de resultado
                'selfEvaluationRequestId' => null,
                'bossEvaluationRequestId' => null,
                'teamEvaluationRequestId' => null,
            ]);
        }

        // Busca pendentes normalmente (prazo + pending)
        $selfEvaluationVisible = $estaNoPrazo && EvaluationRequest::where('requested_person_id', $people->id)
            ->where('status', 'pending')
            ->whereHas('evaluation', function ($query) {
                $query->whereIn('type', [
                    'autoavaliaçãoGestor',
                    'autoavaliaçãoComissionado',
                    'autoavaliação',
                ]);
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
                $query->whereIn('type', [
                    'servidor',
                    'gestor',
                    'comissionado',
                ]);
            })
            ->exists();

        // Busca avaliações COMPLETAS (pega o primeiro ID de cada tipo)
        $selfCompletedRequest = EvaluationRequest::where('requested_person_id', $people->id)
            ->where('status', 'completed')
            ->whereHas('evaluation', function ($query) {
                $query->whereIn('type', [
                    'autoavaliaçãoGestor',
                    'autoavaliaçãoComissionado',
                    'autoavaliação',
                ]);
            })
            ->latest()
            ->first();

        $bossCompletedRequest = EvaluationRequest::where('requested_person_id', $people->id)
            ->where('status', 'completed')
            ->whereHas('evaluation', function ($query) {
                $query->where('type', 'chefia');
            })
            ->latest()
            ->first();

        $teamCompletedRequest = EvaluationRequest::where('requested_person_id', $people->id)
            ->where('status', 'completed')
            ->whereHas('evaluation', function ($query) {
                $query->where('type', 'equipe');
            })
            ->latest()
            ->first();

        // Flags booleanos (se tem completed)
        $selfEvaluationCompleted = !!$selfCompletedRequest;
        $bossEvaluationCompleted = !!$bossCompletedRequest;
        $teamEvaluationCompleted = !!$teamCompletedRequest;

        // IDs para o front abrir página de resultado correta
        $selfEvaluationRequestId = $selfCompletedRequest?->id;
        $bossEvaluationRequestId = $bossCompletedRequest?->id;
        $teamEvaluationRequestId = $teamCompletedRequest?->id;

        // Fora do prazo: tudo falso/nulo (opcional, mas bom para segurança)
        if (!$estaNoPrazo) {
            $selfEvaluationVisible = false;
            $bossEvaluationVisible = false;
            $teamEvaluationVisible = false;
            $selfEvaluationCompleted = false;
            $bossEvaluationCompleted = false;
            $teamEvaluationCompleted = false;
            $selfEvaluationRequestId = null;
            $bossEvaluationRequestId = null;
            $teamEvaluationRequestId = null;
        }

        return Inertia::render('Dashboard/Evaluation', [
            'prazo' => $prazo,
            'selfEvaluationVisible' => $selfEvaluationVisible,
            'bossEvaluationVisible' => $bossEvaluationVisible,
            'teamEvaluationVisible' => $teamEvaluationVisible,
            'selfEvaluationCompleted' => $selfEvaluationCompleted,
            'bossEvaluationCompleted' => $bossEvaluationCompleted,
            'teamEvaluationCompleted' => $teamEvaluationCompleted,
            // IDs para visualizar resultado de cada tipo
            'selfEvaluationRequestId' => $selfEvaluationRequestId,
            'bossEvaluationRequestId' => $bossEvaluationRequestId,
            'teamEvaluationRequestId' => $teamEvaluationRequestId,
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

    public function recourse()
    {

        if (!user_can('recourse')) {
            return redirect()->route('evaluations')->with('error', 'Você não tem permissão para acessar essa área.');
        }

        // Busca o prazo apenas para o grupo de PDI
        $recourse = $this->getGroupDeadline('recourse');

        return Inertia::render('Dashboard/Recourse', [
            'recourse' => $recourse,
        ]);
    }

    /**
     * ***** MÉTODO DO CALENDÁRIO ATUALIZADO *****
     */
    public function calendar()
    {
        if (!user_can('calendar')) {
            return redirect()->route('evaluations')->with('error', 'Você não tem permissão para acessar essa área.');
        }

        // 1. Busca os prazos dos formulários
        $prazos = Form::whereNotNull('term_first')
            ->whereNotNull('term_end')
            ->select('year', 'type', 'term_first', 'term_end')
            ->get()
            ->groupBy([
                'year',
                function ($item) {
                    $pdiTypes = ['pactuacao_servidor', 'pactuacao_comissionado', 'pactuacao_gestor'];
                    return in_array($item->type, $pdiTypes) ? 'pdi' : 'avaliacao';
                }
            ])
            ->map(function ($yearGroup) {
                return $yearGroup->map(function ($group, $groupName) {
                    $firstForm = $group->first();
                    return [
                        'start' => $firstForm->term_first->toDateString(),
                        'end' => $firstForm->term_end->toDateString(),
                        'title' => 'Período ' . ucfirst($groupName) . ' ' . $firstForm->year,
                        'group' => $groupName,
                    ];
                });
            })
            ->flatten(1)
            ->values()
            ->toArray();

        // 2. Busca as configurações de datas
        $config = Config::first();
        $newEvents = [];

        if ($config) {
            // 3. Cria o evento para a "Divulgação das Notas"
            if ($config->gradesPeriod) {
                $gradesDate = Carbon::parse($config->gradesPeriod);
                $newEvents[] = [
                    'start' => $gradesDate->toDateString(),
                    'end' => $gradesDate->toDateString(),
                    'title' => 'Divulgação das Notas',
                    'group' => 'divulgacao',
                ];
            }

            // 4. Cria o evento para o "Período de Ciência"
            if ($config->gradesPeriod && isset($config->awarePeriod)) {
                $startDate = Carbon::parse($config->gradesPeriod)->addDay();
                $endDate = $startDate->copy()->addDays($config->awarePeriod - 1);

                $newEvents[] = [
                    'start' => $startDate->toDateString(),
                    'end' => $endDate->toDateString(),
                    'title' => 'Período de Ciência da Nota',
                    'group' => 'ciencia',
                ];
            }
        }

        // 5. Junta todos os eventos
        $allEvents = array_merge($prazos, $newEvents);

        return Inertia::render('Dashboard/Calendar', [
            'deadlineEvents' => $allEvents,
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

        return Inertia::render('Dashboard/Configs', [
            'forms' => $forms,
            'existingYears' => $existingYears,
        ]);
    }
}