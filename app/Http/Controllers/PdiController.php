<?php

namespace App\Http\Controllers;

use App\Models\PdiAnswer;
use App\Models\PdiRequest;
use App\Models\Person;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Log;

class PdiController extends Controller
{
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
        
        // PDIs que o usuário (como gestor) precisa preencher
        $pdisToFill = PdiRequest::with('person', 'pdi.form')
            ->where('manager_id', $person->id)
            ->where('status', 'pending_manager_fill')
            ->get();

        // PDIs que o usuário (como servidor) precisa assinar
        $pdisToSign = PdiRequest::with('manager', 'pdi.form')
            ->where('person_id', $person->id)
            ->where('status', 'pending_employee_signature')
            ->get();
            
        // PDIs concluídos
        $pdisCompleted = PdiRequest::with('manager', 'person', 'pdi.form')
             ->where(function($query) use ($person) {
                 $query->where('person_id', $person->id)
                       ->orWhere('manager_id', $person->id);
             })
            ->where('status', 'completed')
            ->get();
            

        return Inertia::render('PDI/PdiList', [
            'pdisToFill' => $pdisToFill,
            'pdisToSign' => $pdisToSign,
            'pdisCompleted' => $pdisCompleted,
        ]);
    }

    // Mostra o formulário de PDI para preenchimento ou ciência
    public function show(PdiRequest $pdiRequest)
    {
        // Carrega os relacionamentos, incluindo as respostas já salvas
        $pdiRequest->load([
            'pdi.form.groupQuestions.questions',
            'answers', // Carrega as respostas da relação correta em PdiRequest
            'person.jobFunction',
            'manager.jobFunction'
        ]);
        
     
        return Inertia::render('PDI/PdiFormPage', [
            'pdiRequest' => $pdiRequest,
            'pdiAnswers' => $pdiRequest->answers,
        ]);
    }

    // Atualiza o PDI (preenchimento do gestor ou assinatura do servidor)
    public function update(Request $request, PdiRequest $pdiRequest)
    {
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
                foreach($validated['answers'] as $answerData) {
                    
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
}