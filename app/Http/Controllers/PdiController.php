<?php

namespace App\Http\Controllers;

use App\Models\Pdi;
use App\Models\PdiRequest;
use App\Models\Person;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class PdiController extends Controller
{
    // Mostra a lista de PDIs para o usuário (seja ele gestor ou servidor)
    public function index()
    {
         
        $user = Auth::user();
        $cpf= 10798101610;
        
        if (!$user || !$user->cpf) {
            return redirect()->route('dashboard')->with('error', 'Seu usuário não possui um CPF configurado.');
        }

        $person = Person::where('cpf', $cpf)->firstOrFail();
        
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
        $pdiRequest->load(['pdi.form', 'person.jobFunction', 'manager.jobFunction']);

        return Inertia::render('PDI/PdiFormPage', [
            'pdiRequest' => $pdiRequest,
        ]);
    }

    // Atualiza o PDI (preenchimento do gestor ou assinatura do servidor)
    public function update(Request $request, PdiRequest $pdiRequest)
    {
        $validated = $request->validate([
            'signature_base64' => 'required|string',
            // Valida os campos do PDI apenas se o gestor estiver preenchendo
            'development_goals' => 'required_if:status,pending_manager_fill|string|nullable',
            'actions_needed' => 'required_if:status,pending_manager_fill|string|nullable',
            'manager_feedback' => 'string|nullable',
        ]);
        
        $person = Person::where('cpf', Auth::user()->cpf)->firstOrFail();

        DB::beginTransaction();
        try {
            // Se o gestor está preenchendo
            if ($pdiRequest->status === 'pending_manager_fill' && $pdiRequest->manager_id === $person->id) {
                
                $pdiRequest->pdi->update([
                    'development_goals' => $validated['development_goals'],
                    'actions_needed' => $validated['actions_needed'],
                    'manager_feedback' => $validated['manager_feedback'],
                ]);

                $pdiRequest->update([
                    'manager_signature_base64' => $validated['signature_base64'],
                    'manager_signed_at' => now(),
                    'status' => 'pending_employee_signature', // Avança para o próximo passo
                ]);

            // Se o servidor está dando ciência
            } elseif ($pdiRequest->status === 'pending_employee_signature' && $pdiRequest->person_id === $person->id) {
                
                $pdiRequest->update([
                    'person_signature_base64' => $validated['signature_base64'],
                    'person_signed_at' => now(),
                    'status' => 'completed', // Finaliza o processo
                ]);

            } else {
                throw new \Exception("Ação não permitida ou status inválido.");
            }

            DB::commit();
            return redirect()->route('pdi.index')->with('success', 'PDI atualizado com sucesso!');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Ocorreu um erro: ' . $e->getMessage());
        }
    }
}