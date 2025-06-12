<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Jobs\ProcessUserUpload; // Importa o novo Job
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class UserController extends Controller
{
    /**
     * Etapa 1: Processa o CSV para gerar um preview das mudanças.
     * (Esta função permanece a mesma)
     */
    public function previewUpload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $file = $request->file('file');
            $tempFileName = 'user_upload_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $tempFilePath = $file->storeAs('temp_uploads', $tempFileName, 'local');

            $absolutePath = Storage::disk('local')->path($tempFilePath);
            $fileHandle = fopen($absolutePath, 'r');

            if (!$fileHandle) {
                throw new \Exception("Não foi possível abrir o arquivo temporário.");
            }

            $header = fgetcsv($fileHandle, 0, ';');
            $rowsData = [];
            while (($row = fgetcsv($fileHandle, 0, ';')) !== false) {
                if (count($header) == count($row)) {
                    $rowsData[] = array_combine($header, $row);
                }
            }
            fclose($fileHandle);

            $registrationNumbers = array_column($rowsData, 'MATRICULA');
            $existingUsers = User::whereIn('registration_number', $registrationNumbers)->get()->keyBy('registration_number');

            $summary = [
                'new' => 0,
                'updated' => 0,
                'unchanged' => 0,
                'errors' => 0,
                'skipped_interns' => 0,
            ];
            $detailedChanges = [];
            $errorsList = [];

            foreach ($rowsData as $index => $data) {
                try {
                    $userData = $this->transformData($data, false);

                    if (isset($userData['bond_type']) && strcasecmp($userData['bond_type'], 'ESTAGIARIO') == 0) {
                        $summary['skipped_interns']++;
                        continue;
                    }

                    $this->validateRow($userData, $index + 2);
                    $existingUser = $existingUsers->get($userData['registration_number']);

                    if ($existingUser) {
                        $diff = $this->compareUserData($existingUser, $userData);
                        if (empty($diff)) {
                            $summary['unchanged']++;
                        } else {
                            $summary['updated']++;
                            $detailedChanges[] = [
                                'status' => 'updated',
                                'name' => $userData['name'],
                                'registration_number' => $userData['registration_number'],
                                'changes' => $diff
                            ];
                        }
                    } else {
                        $summary['new']++;
                        $detailedChanges[] = [
                            'status' => 'new',
                            'name' => $userData['name'],
                            'registration_number' => $userData['registration_number'],
                            'data' => $userData,
                        ];
                    }
                } catch (ValidationException $e) {
                    $summary['errors']++;
                    $errorMessages = Arr::flatten($e->errors());
                    $errorsList[] = "Erro na linha " . ($index + 2) . ": " . implode(', ', $errorMessages);
                }
            }

            return response()->json([
                'message' => 'Pré-visualização gerada com sucesso.',
                'summary' => $summary,
                'detailed_changes' => $detailedChanges,
                'errors' => $errorsList,
                'temp_file_path' => $tempFilePath,
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao gerar preview de usuários: ' . $e->getMessage());
            return response()->json(['message' => 'Ocorreu um erro inesperado ao processar o arquivo para preview.'], 500);
        }
    }

    /**
     * Etapa 2: Apenas despacha o job para a fila.
     */
    public function confirmUpload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'temp_file_path' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $tempFilePath = $request->input('temp_file_path');

        if (!Storage::disk('local')->exists($tempFilePath) || strpos($tempFilePath, 'temp_uploads/') !== 0) {
            return response()->json(['message' => 'Arquivo temporário inválido ou não encontrado.'], 404);
        }

        // --- ALTERAÇÃO PRINCIPAL AQUI ---
        // Despacha o job para a fila em vez de processar aqui.
        ProcessUserUpload::dispatch($tempFilePath);

        return response()->json([
            'message' => "Seu arquivo foi enviado para processamento em segundo plano. Os usuários serão atualizados em breve."
        ]);
    }

    // Métodos de ajuda (permanecem aqui para a função de preview)

    private function transformData(array $data, bool $hashPassword): array
    {
        $cpf = preg_replace('/[^0-9]/', '', $data['CPF'] ?? '');
        $transformed = [
            'name' => trim($data['NOME'] ?? ''),
            'username' => strtolower(explode('@', $data['EMAIL'] ?? 'user')[0]),
            'email' => trim($data['EMAIL'] ?? ''),
            'registration_number' => trim($data['MATRICULA'] ?? null),
            'bond_type' => trim($data['VINCULO'] ?? null),
            'functional_status' => trim($data['SITUACAO'] ?? null),
            'cpf' => $cpf,
            'rg_number' => trim($data['RG_NUMERO'] ?? null),
            'admission_date' => $this->formatDate($data['ADMISSAO'] ?? null),
            'dismissal_date' => $this->formatDate($data['DEMISSAO'] ?? null),
            'current_position' => trim($data['CARGO'] ?? null),
            'current_function' => trim($data['FUNCAO'] ?? null),
            'allocation_code' => trim($data['LOTACAO'] ?? null),
            'allocation_name' => trim($data['NOME_LOT'] ?? null),
        ];

        if ($hashPassword) {
            $transformed['password'] = Hash::make($cpf);
        }

        return $transformed;
    }

    private function compareUserData(User $user, array $newData): array
    {
        $diff = [];
        $fieldsToCompare = [
            'name',
            'email',
            'bond_type',
            'functional_status',
            'cpf',
            'rg_number',
            'admission_date',
            'dismissal_date',
            'current_position',
            'current_function',
            'allocation_code',
            'allocation_name'
        ];

        foreach ($fieldsToCompare as $field) {
            $userValue = $user->{$field} instanceof Carbon ? $user->{$field}->format('Y-m-d') : $user->{$field};
            $newValue = $newData[$field];

            if ($userValue != $newValue) {
                $diff[$field] = ['from' => $userValue, 'to' => $newValue];
            }
        }
        return $diff;
    }

    private function validateRow(array $data, int $lineNumber)
    {
        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
            'registration_number' => 'required|string|max:255',
            'cpf' => 'required|string|digits:11',
            'email' => 'nullable|email|max:255',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    private function formatDate(?string $dateString): ?string
    {
        if (empty($dateString)) {
            return null;
        }
        try {
            return Carbon::createFromFormat('d/m/Y', $dateString)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    public function cpf()
    {
        $user = auth()->user();


        return Inertia::render('settings/Cpf', [
            'user' => $user, // Esses dados estarão disponíveis como props no componente Dashboard/Index.vue
            // Outros dados necessários para a página
        ]);
    }


    public function cpfUpdate(Request $request)
    {
        $user = Auth::user();

        // Validação dos dados recebidos do formulário
        $validated = $request->validate([
            'cpf' => ['cpf'],
        ]);
        
        // Atualiza o CPF do usuário
        $user->forceFill([
            'cpf' => $validated['cpf'],
        ])->save();

        // Redireciona o usuário para o dashboard com uma mensagem de sucesso
        return Redirect::route('dashboard')->with('success', 'CPF atualizado com sucesso!');
    }
}
