<?php

namespace App\Http\Controllers;

use App\Models\EvaluationRecourse;
use App\Models\Form;
use App\Models\PdiRequest;
use App\Models\Person;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\EvaluationRequest;
use App\Models\configs as Config; // Importar o model de configurações
use Carbon\Carbon; // Importar a classe Carbon

class DashboardController extends Controller
{
    /**
     * Função de ajuda para buscar o prazo de um grupo específico.
     */
    private function getGroupDeadline(string $groupName, ?int $year = null): ?Form
    {
        // Define ano atual padrão (ajustado para janeiro e fevereiro)
        if (!$year) {
            $year = in_array(date('n'), [1, 2]) ? date('Y') - 1 : date('Y');
        }
        $formTypes = [];

        if ($groupName === 'avaliacao') {
            $formTypes = ['servidor', 'gestor', 'chefia', 'comissionado'];
        } elseif ($groupName === 'pdi') {
            $formTypes = ['pactuacao_servidor', 'pactuacao_comissionado', 'pactuacao_gestor'];
        }

        return Form::where('year', $year)
            ->whereIn('type', $formTypes)
            ->where('release', true)
            ->select('term_first', 'term_end')
            ->first();
    }


    /**
     * Exibe a página principal do dashboard.
     */

    public function index(Request $request)
    {
        if (!user_can('dashboard')) {
            return redirect()->route('evaluations');
        }

        // Lista de anos com formulários liberados
        $availableYears = Form::where('release', true)
            ->select('year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->toArray();

        // Ano selecionado via query string (ou padrão)
        $year = $request->input('year', date('Y'));

        $prazoAvaliacao = $this->getGroupDeadline('avaliacao', $year);
        $prazoPdi = $this->getGroupDeadline('pdi', $year);

        $prazoTerminou = false;
        if ($prazoAvaliacao && $prazoAvaliacao->term_first && $prazoAvaliacao->term_end) {
            $hoje = now();
            $inicio = Carbon::parse($prazoAvaliacao->term_first)->startOfDay();
            $fim = Carbon::parse($prazoAvaliacao->term_end)->endOfDay();
            $prazoTerminou = $hoje->greaterThan($fim);
        }

        $completedAssessments = EvaluationRequest::where('status', 'completed')->count();
        $pendingAssessments = EvaluationRequest::where('status', 'pending')->count();
        $unansweredAssessments = $prazoTerminou ? $pendingAssessments : 0;

        if ($prazoTerminou) {
            $pendingAssessments = 0;
        }

        $now = now();
        $currentYear = date('Y');

        $pdiForm = $this->getGroupDeadline('pdi', $currentYear);
        $pdiPrazoFinal = $pdiForm?->term_end ? Carbon::parse($pdiForm->term_end)->endOfDay() : null;
        $pdiExpiredVisible = false;
        if ($pdiPrazoFinal && $now->greaterThan($pdiPrazoFinal)) {
            $pdiExpiredVisible = PdiRequest::where('status', '!=', 'completed')->exists();
        }


        $totalAssessments = $completedAssessments + $pendingAssessments + $unansweredAssessments;
        $overallProgress = ($totalAssessments > 0)
            ? ($completedAssessments / $totalAssessments) * 100
            : 0;

        $formattedProgress = $overallProgress == 0
            ? 0
            : rtrim(rtrim(number_format($overallProgress, 3, '.', ''), '0'), '.');

        $dashboardStats = [
            'completedAssessments' => $completedAssessments,
            'pendingAssessments' => $pendingAssessments,
            'unansweredAssessments' => $unansweredAssessments,
            'overallProgress' => $formattedProgress . '%',
        ];

        return Inertia::render('Dashboard/Index', [
            'selectedYear' => (int) $year,
            'availableYears' => $availableYears,
            'prazoAvaliacao' => $prazoAvaliacao,
            'prazoPdi' => $prazoPdi,
            'dashboardStats' => $dashboardStats,
            'pdiExpiredVisible' => $pdiExpiredVisible,
        ]);
    }


    /**
     * Exibe o dashboard de avaliação do usuário com a lógica completa.
     * Esta função agora contém a lógica movida de EvaluationController@showDashboard.
     */
    public function evaluation()
    {
        $user = Auth::user();
        if (!$user || !$user->cpf) {
            abort(403, 'Usuário não autenticado ou sem CPF.');
        }

        $person = Person::where('cpf', $user->cpf)->first();
        if (!$person) {
            // Se a pessoa não for encontrada, renderiza a página com tudo desabilitado.
            return Inertia::render('Dashboard/Evaluation', [
                'prazo' => null,
                'selfEvaluationVisible' => false,
                'bossEvaluationVisible' => false,
                'teamEvaluationVisible' => false,
                'selfEvaluationCompleted' => false,
                'bossEvaluationCompleted' => false,
                'teamEvaluationCompleted' => false,
                'selfEvaluationRequestId' => null,
                'bossEvaluationRequestId' => null,
                'teamEvaluationRequestId' => null,
                'recourseLink' => null,
            ]);
        }

        $now = now();
        $currentYear = date('Y');

        // --- Lógica para a Autoavaliação ---
        $selfForm = Form::where('year', $currentYear)->whereIn('type', ['servidor', 'gestor', 'comissionado'])->where('release', true)->first();
        $isWithinSelfStandardPeriod = $selfForm && $selfForm->term_first && $selfForm->term_end && $now->between(Carbon::parse($selfForm->term_first)->startOfDay(), Carbon::parse($selfForm->term_end)->endOfDay());

        $selfEvalRequest = EvaluationRequest::where('requested_person_id', $person->id)
            ->whereHas('evaluation', function ($q) {
                $q->whereIn('type', ['autoavaliação', 'autoavaliaçãoGestor', 'autoavaliaçãoComissionado']);
            })
            ->first();

        $selfEvaluationCompleted = $selfEvalRequest && $selfEvalRequest->status === 'completed';
        $isSelfEvalInException = $selfEvalRequest && $selfEvalRequest->exception_date_first && $selfEvalRequest->exception_date_end && $now->between(Carbon::parse($selfEvalRequest->exception_date_first)->startOfDay(), Carbon::parse($selfEvalRequest->exception_date_end)->endOfDay());

        // CORREÇÃO: Só mostra o botão se existe uma autoavaliação para fazer E está no prazo
        $selfEvaluationVisible = $selfEvalRequest && !$selfEvaluationCompleted && ($isWithinSelfStandardPeriod || $isSelfEvalInException);

        // --- Lógica para a Avaliação da Chefia ---
        $bossForm = Form::where('year', $currentYear)->where('type', 'chefia')->where('release', true)->first();
        $isWithinBossStandardPeriod = $bossForm && $bossForm->term_first && $bossForm->term_end && $now->between(Carbon::parse($bossForm->term_first)->startOfDay(), Carbon::parse($bossForm->term_end)->endOfDay());

        $bossEvalRequest = EvaluationRequest::where('requested_person_id', $person->id)
            ->whereHas('evaluation', function ($q) {
                $q->where('type', 'chefia');
            })
            ->first();

        $bossEvaluationCompleted = $bossEvalRequest && $bossEvalRequest->status === 'completed';
        $isBossEvalInException = $bossEvalRequest && $bossEvalRequest->exception_date_first && $bossEvalRequest->exception_date_end && $now->between(Carbon::parse($bossEvalRequest->exception_date_first)->startOfDay(), Carbon::parse($bossEvalRequest->exception_date_end)->endOfDay());

        // CORREÇÃO: Só mostra o botão se existe uma avaliação de chefia para fazer E está no prazo
        $bossEvaluationVisible = $bossEvalRequest && !$bossEvaluationCompleted && ($isWithinBossStandardPeriod || $isBossEvalInException);

        // --- LÓGICA CORRIGIDA para Avaliar Equipe (se for gestor) ---
        // Só deve mostrar se a pessoa tem subordinados E tem avaliações pendentes desses subordinados
        $subordinateIds = Person::where('direct_manager_id', $person->id)->pluck('id');
        
        // CORREÇÃO: A pessoa é REQUESTED (vai avaliar), não requester
        $pendingTeamRequests = EvaluationRequest::where('requested_person_id', $person->id)
            ->whereHas('evaluation', function ($q) use ($subordinateIds) {
                $q->whereIn('type', ['servidor', 'gestor', 'comissionado'])
                  ->whereIn('evaluated_person_id', $subordinateIds); // Só subordinados
            })
            ->where('status', 'pending')
            ->get();

        $teamEvaluationVisible = false;
        foreach ($pendingTeamRequests as $request) {
            $isTeamEvalInException = $request->exception_date_first &&
                $request->exception_date_end &&
                $now->between(
                    Carbon::parse($request->exception_date_first)->startOfDay(),
                    Carbon::parse($request->exception_date_end)->endOfDay()
                );

            if ($isWithinSelfStandardPeriod || $isTeamEvalInException) {
                $teamEvaluationVisible = true;
                break;
            }
        }

        $teamEvaluationCompleted = !$teamEvaluationVisible && 
            $subordinateIds->count() > 0 && 
            EvaluationRequest::where('requested_person_id', $person->id)
                ->whereHas('evaluation', function ($q) use ($subordinateIds) {
                    $q->whereIn('type', ['servidor', 'gestor', 'comissionado'])
                      ->whereIn('evaluated_person_id', $subordinateIds); // Só subordinados
                })->exists();

        // --- Prazo Geral (para exibição no card de data) ---
        $mainFormForPrazo = $selfForm ?? $bossForm;
        $prazo = null;
        if ($mainFormForPrazo) {
            $prazo = [
                'term_first' => $mainFormForPrazo->term_first ? Carbon::parse($mainFormForPrazo->term_first)->format('Y-m-d') : null,
                'term_end' => $mainFormForPrazo->term_end ? Carbon::parse($mainFormForPrazo->term_end)->format('Y-m-d') : null
            ];
        }

        // --- Lógica para Link de Recurso ---
        $recourse = EvaluationRecourse::with('evaluation.form')
            ->where('person_id', $person->id)
            ->latest()
            ->first();

        // Renderiza a view de avaliação principal com todos os dados calculados.
        return Inertia::render('Dashboard/Evaluation', [
            'prazo' => $prazo,
            'selfEvaluationVisible' => $selfEvaluationVisible,
            'bossEvaluationVisible' => $bossEvaluationVisible,
            'teamEvaluationVisible' => $teamEvaluationVisible,
            'selfEvaluationCompleted' => $selfEvaluationCompleted,
            'bossEvaluationCompleted' => $bossEvaluationCompleted,
            'teamEvaluationCompleted' => $teamEvaluationCompleted,
            'selfEvaluationRequestId' => $selfEvalRequest?->id,
            'bossEvaluationRequestId' => $bossEvalRequest?->id,
            'teamEvaluationRequestId' => null, // Este ID pode ser aprimorado se necessário
            'recourseLink' => $recourse ? route('recourses.show', $recourse->id) : null,
        ]);

    }

    public function pdi()
    {
        $user = Auth::user();
        $pdiStatus = 'not_released'; // Status padrão
        $prazoPdi = null; // Prazo padrão

        // Busca a pessoa pelo CPF do usuário logado
        $person = Person::where('cpf', $user->cpf)->first();

        // Verifica se é gestor (ajuste conforme sua lógica de permissão)
        $isManager = $person && $person->job_function_id; // Supondo que exista o campo is_manager

        // Se a pessoa existe, verificamos o status do PDI
        if ($person) {
            // Busca o PDI mais recente do ano corrente para o servidor
            $pdiRequest = PdiRequest::where('person_id', $person->id)
                ->whereHas('pdi', function ($query) {
                    $query->where('year', now()->year);
                })
                ->first();

            if ($pdiRequest) {
                if ($pdiRequest->status === 'pending_manager_fill') {
                    $pdiStatus = 'pending_manager'; // Gestor ainda não preencheu
                } else {
                    $pdiStatus = 'available'; // PDI disponível para o servidor
                }
            }
        } else {
            $pdiStatus = 'user_not_found'; // Usuário sem cadastro em 'people'
        }

        // Se for gestor, carrega a lista de servidores sob sua gestão
        $managedServers = [];
        if ($isManager) {
            $managedServers = Person::where('manager_id', $person->id)->get(); // Ajuste o campo conforme seu banco
        }

        $year = (in_array(date('n'), [1, 2])) ? date('Y') - 1 : date('Y');
        $prazoPdi = $this->getGroupDeadline('pdi', $year);

        return Inertia::render('Dashboard/PDI', [
            'pdiStatus' => $pdiStatus,
            'prazoPdi' => $prazoPdi,
            'managedServers' => $managedServers, // Envia a lista para o front
            'isManager' => $isManager,
        ]);
    }

    public function recourse()
    {
        $user = Auth::user();
        $isRH = user_can('recourse');

        // Verifica se é RH ou se tem role "Comissão"
        $isComissao = $user && $user->roles->pluck('name')->contains('Comissão');

        if (!$isRH && !$isComissao) {
            return redirect()->route('evaluations')->with('error', 'Você não tem permissão para acessar essa área.');
        }

        $recourse = $this->getGroupDeadline('recourse');

        // PRIORIDADE: Se tem role "Comissão", trata como Comissão mesmo que tenha permissão RH
        if ($isComissao) {
            // Se for da Comissão, mostra apenas os recursos pelos quais é responsável
            $person = Person::where('cpf', $user->cpf)->first();

            if (!$person) {
                return redirect()->route('evaluations')->with('error', 'Dados de pessoa não encontrados.');
            }

            // Busca apenas recursos onde a pessoa é responsável
            $responsibleRecourses = EvaluationRecourse::whereHas('responsiblePersons', function ($q) use ($person) {
                $q->where('person_id', $person->id);
            });

            $total = $responsibleRecourses->count();
            $responded = (clone $responsibleRecourses)->where('status', 'respondido')->count();
            $denied = (clone $responsibleRecourses)->where('status', 'indeferido')->count();

            return Inertia::render('Dashboard/Recourse', [
                'recourse' => $recourse,
                'totals' => [
                    'opened' => (clone $responsibleRecourses)->where('status', 'aberto')->count(),
                    'under_analysis' => (clone $responsibleRecourses)->where('status', 'em_analise')->count(),
                    'responded' => $responded,
                    'denied' => $denied,
                    'analyzed_percent' => $total > 0 ? round($responded / $total * 100) . '%' : '0%',
                ],
                'userRole' => 'Comissão',
            ]);
        }

        // Se for RH (e não tem role Comissão), mostra todos os recursos
        if ($isRH) {
            $total = EvaluationRecourse::count();
            $responded = EvaluationRecourse::where('status', 'respondido')->count();
            $denied = EvaluationRecourse::where('status', 'indeferido')->count();

            return Inertia::render('Dashboard/Recourse', [
                'recourse' => $recourse,
                'totals' => [
                    'opened' => EvaluationRecourse::where('status', 'aberto')->count(),
                    'under_analysis' => EvaluationRecourse::where('status', 'em_analise')->count(),
                    'responded' => $responded,
                    'denied' => $denied,
                    'analyzed_percent' => $total > 0 ? round($responded / $total * 100) . '%' : '0%',
                ],
                'userRole' => 'RH',
            ]);
        }
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

        $configs = Config::all()->keyBy('year');

        return Inertia::render('Dashboard/Configs', [
            'forms' => $forms,
            'existingYears' => $existingYears,
            'configs' => $configs
        ]);
    }

}