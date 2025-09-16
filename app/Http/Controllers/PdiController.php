<?php

namespace App\Http\Controllers;

use App\Models\PdiAnswer;
use App\Models\PdiRequest;
use App\Models\Person;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use App\Models\Form;
use App\Models\User;
use Carbon\Carbon;

class PdiController extends Controller
{
    /**
     * Verifica se um PDI pode ser interagido (preenchido/assinado)
     */
    private function canInteractWithPdi(PdiRequest $pdiRequest): bool
    {
        $currentYear = date('Y');
        $pdiForm = DB::table('forms')
            ->whereIn('type', ['pactuacao_servidor', 'pactuacao_gestor', 'pactuacao_comissionado'])
            ->whereIn('year', [$currentYear, $currentYear - 1])
            ->orderBy('year', 'desc')
            ->select('term_end', 'year')
            ->first();

        if (!$pdiForm || !$pdiForm->term_end) {
            return true; // Se não há prazo definido, pode interagir
        }

        $pdiPrazoFinal = Carbon::parse($pdiForm->term_end)->endOfDay();
        $now = now();

        // Se ainda está no prazo geral
        if ($now->lessThanOrEqualTo($pdiPrazoFinal)) {
            return true;
        }

        // Se passou do prazo geral, verificar se foi liberado
        if (!$pdiRequest->exception_date_first || !$pdiRequest->exception_date_end) {
            return false; // Não foi liberado
        }

        // Verificar se está no período de exceção
        $exceptionStart = Carbon::parse($pdiRequest->exception_date_first)->startOfDay();
        $exceptionEnd = Carbon::parse($pdiRequest->exception_date_end)->endOfDay();

        return $now->between($exceptionStart, $exceptionEnd);
    }
    // Mostra a lista de PDIs para o usuário (seja ele gestor ou servidor)
    public function index()
    {
        $user = Auth::user();

        if (!$user || !$user->cpf) {
            return redirect()->route('dashboard')->with('error', 'Seu usuário não possui um CPF configurado.');
        }

        $person = Person::where('cpf', $user->cpf)->firstOrFail();

        if (!$person) {
            return redirect()->route('dashboard')->with('error', 'Seu cadastro de servidor não foi encontrado. Entre em contato com o RH.');
        }

        // Obter informações de prazo para verificação
        $currentYear = date('Y');
        $pdiForm = DB::table('forms')
            ->whereIn('type', ['pactuacao_servidor', 'pactuacao_gestor', 'pactuacao_comissionado'])
            ->whereIn('year', [$currentYear, $currentYear - 1])
            ->orderBy('year', 'desc')
            ->select('term_end', 'year')
            ->first();
            
        $pdiPrazoFinal = $pdiForm?->term_end ? Carbon::parse($pdiForm->term_end)->endOfDay() : null;
        $now = now();

        // Helper function para adicionar informações de prazo
        $addDeadlineInfo = function ($pdiRequest) use ($pdiPrazoFinal, $now) {
            $canInteract = true;
            $isOutOfDeadline = false;
            
            if ($pdiPrazoFinal && $now->greaterThan($pdiPrazoFinal)) {
                if (!$pdiRequest->exception_date_first || !$pdiRequest->exception_date_end) {
                    $isOutOfDeadline = true;
                    $canInteract = false;
                } else {
                    $exceptionStart = Carbon::parse($pdiRequest->exception_date_first)->startOfDay();
                    $exceptionEnd = Carbon::parse($pdiRequest->exception_date_end)->endOfDay();
                    
                    if ($now->between($exceptionStart, $exceptionEnd)) {
                        $canInteract = true;
                        $isOutOfDeadline = false;
                    } else {
                        $isOutOfDeadline = true;
                        $canInteract = false;
                    }
                }
            }
            
            $pdiRequest->can_interact = $canInteract;
            $pdiRequest->is_out_of_deadline = $isOutOfDeadline;
            
            return $pdiRequest;
        };

        // PDIs que o usuário (como gestor) precisa preencher
        $pdisToFill = PdiRequest::with('person', 'pdi.form')
            ->where('manager_id', $person->id)
            ->where('status', 'pending_manager_fill')
            ->get()
            ->map($addDeadlineInfo);

        // PDIs que o usuário (como servidor) precisa assinar
        $pdisToSign = PdiRequest::with('manager', 'pdi.form')
            ->where('person_id', $person->id)
            ->where('status', 'pending_employee_signature')
            ->get()
            ->map($addDeadlineInfo);

        // PDIs preenchidos pelo gestor (aguardando assinatura do servidor)
        $pdisPendingEmployeeSignature = PdiRequest::with('person', 'pdi.form')
            ->where('manager_id', $person->id)
            ->where('status', 'pending_employee_signature')
            ->get()
            ->map($addDeadlineInfo);

        // PDIs concluídos
        $pdisCompleted = PdiRequest::with('manager', 'person', 'pdi.form')
            ->where(function ($query) use ($person) {
                $query->where('person_id', $person->id)
                    ->orWhere('manager_id', $person->id);
            })
            ->where('status', 'completed')
            ->get()
            ->map($addDeadlineInfo);


        return Inertia::render('PDI/PdiList', [
            'pdisToFill' => $pdisToFill,
            'pdisToSign' => $pdisToSign,
            'pdisPendingEmployeeSignature' => $pdisPendingEmployeeSignature,
            'pdisCompleted' => $pdisCompleted,
        ]);
    }

    // Mostra o formulário de PDI para preenchimento ou ciência
    public function show(PdiRequest $pdiRequest)
    {
        // Verificar se pode interagir com o PDI (mas não bloquear visualização)
        $canInteract = $this->canInteractWithPdi($pdiRequest);

        // Carrega os relacionamentos, incluindo as respostas já salvas
        $pdiRequest->load([
            'pdi.form.groupQuestions.questions',
            'answers', // Carrega as respostas da relação correta em PdiRequest
            'person.jobFunction',
            'manager.jobFunction'
        ]);

        // Busca a pessoa logada pelo CPF para usar no frontend
        $user = Auth::user();
        $loggedPerson = null;
        if ($user && $user->cpf) {
            $loggedPerson = Person::where('cpf', $user->cpf)->first();
        }

        return Inertia::render('PDI/PdiFormPage', [
            'pdiRequest' => $pdiRequest,
            'pdiAnswers' => $pdiRequest->answers,
            'loggedPerson' => $loggedPerson,
            'canInteract' => $canInteract,
        ]);
    }

    // Atualiza o PDI (preenchimento do gestor ou assinatura do servidor)
    public function update(Request $request, PdiRequest $pdiRequest)
    {
        // Verificar se pode interagir com o PDI
        if (!$this->canInteractWithPdi($pdiRequest)) {
            return back()->with('error', 'Este PDI está fora do prazo e não foi liberado para preenchimento/assinatura.');
        }

        $user = Auth::user();

        $person = Person::where('cpf', $user->cpf)->firstOrFail();

        // Se o gestor está preenchendo, valida as respostas e a assinatura
        if ($pdiRequest->status === 'pending_manager_fill' && $pdiRequest->manager_id === $person->id) {
            $validated = $request->validate([
                'signature_base64' => 'required|string',
                'answers' => 'required|array',
                'answers.*.question_id' => 'required|integer|exists:questions,id',
                'answers.*.response_content' => 'nullable|string',
            ]);
            // Se o servidor está assinando, valida apenas a assinatura
        } elseif ($pdiRequest->status === 'pending_employee_signature' && $pdiRequest->person_id === $person->id) {
            $validated = $request->validate([
                'signature_base64' => 'required|string',
            ]);
        } else {
            // Se nenhuma das condições for válida, a permissão é negada.
            abort(403, 'Ação não permitida ou status inválido.');
        }

        DB::beginTransaction();
        try {
            // Se o gestor está preenchendo
            if ($pdiRequest->status === 'pending_manager_fill' && $pdiRequest->manager_id === $person->id) {
                ;
                // Salva ou atualiza cada resposta
                foreach ($validated['answers'] as $answerData) {

                    PdiAnswer::updateOrCreate(
                        [
                            'pdi_request_id' => $pdiRequest->id,
                            'question_id' => $answerData['question_id'],
                        ],
                        [
                            'response_content' => $answerData['response_content'],

                        ]

                    );

                }

                $pdiRequest->update([
                    'manager_signature_base64' => $validated['signature_base64'],
                    'manager_signed_at' => now(),
                    'status' => 'pending_employee_signature',
                ]);

                // Se o servidor está dando ciência
            } elseif ($pdiRequest->status === 'pending_employee_signature' && $pdiRequest->person_id === $person->id) {

                $pdiRequest->update([
                    'person_signature_base64' => $validated['signature_base64'],
                    'person_signed_at' => now(),
                    'status' => 'completed',
                ]);

            }

            DB::commit();
            return redirect()->route('pdi.index')->with('success', 'PDI atualizado com sucesso!');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Ocorreu um erro: ' . $e->getMessage());
        }
    }
    /**
     * Mostra a lista de PDIs que não foram respondidos e cujo prazo expirou.
     */
    public function unanswered(Request $request)
    {
        $year = in_array(date('n'), [1, 2]) ? date('Y') - 1 : date('Y');

        $pdiForm = Form::where('year', $year)
            ->whereIn('type', ['pactuacao_servidor', 'pactuacao_comissionado', 'pactuacao_gestor'])
            ->where('release', true)
            ->select('term_end')
            ->first();

        $pdiPrazoFinal = $pdiForm?->term_end ? Carbon::parse($pdiForm->term_end)->endOfDay() : null;

        // Se ainda está no prazo, redireciona
        if (!$pdiPrazoFinal || now()->lessThanOrEqualTo($pdiPrazoFinal)) {
            return redirect()->route('pdi.index')->with('error', 'Os PDIs ainda estão dentro do prazo de preenchimento.');
        }

        $query = PdiRequest::with(['person', 'manager', 'userReleased'])
            ->where('status', '!=', 'completed');

        $pendingExpired = $query
            ->orderByDesc('created_at')
            ->paginate(20)
            ->through(function ($pdiRequest) {
                return [
                    'id' => $pdiRequest->id,
                    'person_name' => $pdiRequest->person->name ?? 'N/A',
                    'manager_name' => $pdiRequest->manager->name ?? 'N/A',
                    'status' => $pdiRequest->status,
                    'created_at' => $pdiRequest->created_at->format('d/m/Y'),
                    'is_released' => !is_null($pdiRequest->exception_date_first),
                    'exception_date_first' => $pdiRequest->exception_date_first,
                    'exception_date_end' => $pdiRequest->exception_date_end,
                    'released_by_name' => $pdiRequest->userReleased->name ?? null,
                ];
            })
            ->withQueryString();

        return Inertia::render('PDI/PendingExpired', [
            'pendingRequests' => $pendingExpired,
            'filters' => $request->only('search'),
        ]);
    }

    /**
     * Libera um PDI com um prazo de exceção.
     */
    public function release(Request $request)
    {
        // Verificar se o usuário tem permissão para liberar PDIs
        if (!user_can('configs')) {
            return back()->with('error', 'Você não tem permissão para liberar PDIs.');
        }

        $data = $request->validate([
            'requestId' => 'required|exists:pdi_requests,id',
            'exceptionDateFirst' => 'required|date',
            'exceptionDateEnd' => 'required|date|after_or_equal:exceptionDateFirst',
        ]);

        $pdiRequest = PdiRequest::findOrFail($data['requestId']);

        $pdiRequest->update([
            'exception_date_first' => $data['exceptionDateFirst'],
            'exception_date_end' => $data['exceptionDateEnd'],
            'released_by' => auth()->id(),
        ]);

        return back()->with('success', 'PDI liberado com um novo prazo!');
    }

    /**
     * Lista todos os PDIs concluídos
     */
    public function completed(Request $request)
    {
        $query = PdiRequest::with(['person', 'manager', 'pdi.form'])
            ->where('status', 'completed');

        // Filtros
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->whereHas('person', function ($subQuery) use ($request) {
                    $subQuery->where('name', 'like', '%' . $request->search . '%');
                })->orWhereHas('manager', function ($subQuery) use ($request) {
                    $subQuery->where('name', 'like', '%' . $request->search . '%');
                })->orWhereHas('pdi.form', function ($subQuery) use ($request) {
                    $subQuery->where('name', 'like', '%' . $request->search . '%');
                });
            });
        }

        if ($request->year) {
            $query->whereHas('pdi', function ($subQuery) use ($request) {
                $subQuery->where('year', $request->year);
            });
        }

        $completedPdis = $query->orderBy('updated_at', 'desc')
            ->paginate(15)
            ->through(function ($pdi) {
                return [
                    'id' => $pdi->id,
                    'pdi_year' => $pdi->pdi->year ?? 'N/A',
                    'form_name' => $pdi->pdi->form->name ?? 'N/A',
                    'person_name' => $pdi->person->name ?? 'N/A',
                    'manager_name' => $pdi->manager->name ?? 'N/A',
                    'status' => $pdi->status,
                    'completed_at' => $pdi->updated_at->format('d/m/Y H:i'),
                ];
            })
            ->withQueryString();

        // Anos disponíveis para filtro
        $availableYears = PdiRequest::where('status', 'completed')
            ->with('pdi')
            ->get()
            ->pluck('pdi.year')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return Inertia::render('PDI/Completed', [
            'completedPdis' => $completedPdis,
            'filters' => $request->only(['search', 'year']),
            'availableYears' => $availableYears,
        ]);
    }

    /**
     * Lista todos os PDIs pendentes
     */
    public function pending(Request $request)
    {
        
        $query = PdiRequest::with(['person', 'manager', 'pdi.form'])
            ->whereIn('status', ['pending_manager_fill', 'pending_employee_signature']);

        // Filtros
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->whereHas('person', function ($subQuery) use ($request) {
                    $subQuery->where('name', 'like', '%' . $request->search . '%');
                })->orWhereHas('manager', function ($subQuery) use ($request) {
                    $subQuery->where('name', 'like', '%' . $request->search . '%');
                })->orWhereHas('pdi.form', function ($subQuery) use ($request) {
                    $subQuery->where('name', 'like', '%' . $request->search . '%');
                });
            });
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->year) {
            $query->whereHas('pdi', function ($subQuery) use ($request) {
                $subQuery->where('year', $request->year);
            });
        }

        // Obter prazo geral do PDI para verificar se está fora do prazo
        $currentYear = date('Y');
        // Verificar primeiro o ano atual, depois o anterior (caso os PDIs sejam do ano passado)
        $pdiForm = DB::table('forms')
            ->whereIn('type', ['pactuacao_servidor', 'pactuacao_gestor', 'pactuacao_comissionado'])
            ->whereIn('year', [$currentYear, $currentYear - 1])
            ->orderBy('year', 'desc')
            ->select('term_end', 'year')
            ->first();
        
        $pdiPrazoFinal = $pdiForm?->term_end ? Carbon::parse($pdiForm->term_end)->endOfDay() : null;
        $now = now();
        
        $pendingPdis = $query->orderBy('created_at', 'desc')
            ->paginate(15)
            ->through(function ($pdi) use ($pdiPrazoFinal, $now) {
                // Verificar se está fora do prazo
                $isOutOfDeadline = false;
                $canInteract = true; // Por padrão, pode interagir
                
                if ($pdiPrazoFinal && $now->greaterThan($pdiPrazoFinal)) {
                    // Se passou do prazo geral
                    if (!$pdi->exception_date_first || !$pdi->exception_date_end) {
                        // Se não foi liberado (sem datas de exceção), não pode interagir
                        $isOutOfDeadline = true;
                        $canInteract = false;
                    } else {
                        // Se foi liberado, verificar se ainda está no período de exceção
                        $exceptionStart = Carbon::parse($pdi->exception_date_first)->startOfDay();
                        $exceptionEnd = Carbon::parse($pdi->exception_date_end)->endOfDay();
                        
                        if ($now->between($exceptionStart, $exceptionEnd)) {
                            // Dentro do período de exceção, pode interagir
                            $canInteract = true;
                            $isOutOfDeadline = false;
                        } else {
                            // Fora do período de exceção, não pode interagir
                            $isOutOfDeadline = true;
                            $canInteract = false;
                        }
                    }
                }

                return [
                    'id' => $pdi->id,
                    'pdi_year' => $pdi->pdi->year ?? 'N/A',
                    'form_name' => $pdi->pdi->form->name ?? 'N/A',
                    'person_name' => $pdi->person->name ?? 'N/A',
                    'manager_name' => $pdi->manager->name ?? 'N/A',
                    'status' => $pdi->status,
                    'created_at' => $pdi->created_at->format('d/m/Y H:i'),
                    'is_out_of_deadline' => $isOutOfDeadline,
                    'can_interact' => $canInteract,
                    'exception_date_first' => $pdi->exception_date_first,
                    'exception_date_end' => $pdi->exception_date_end,
                ];
            })
            ->withQueryString();

        // Anos disponíveis para filtro
        $availableYears = PdiRequest::whereIn('status', ['pending_manager_fill', 'pending_employee_signature'])
            ->with('pdi')
            ->get()
            ->pluck('pdi.year')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        // Status disponíveis para filtro
        $availableStatuses = ['pending_manager_fill', 'pending_employee_signature'];

        return Inertia::render('PDI/Pending', [
            'pendingPdis' => $pendingPdis,
            'filters' => $request->only(['search', 'status', 'year']),
            'availableYears' => $availableYears,
            'availableStatuses' => $availableStatuses,
            'canReleasePdis' => user_can('configs'),
        ]);
    }
}