<?php

namespace App\Http\Controllers;

use App\Models\Person;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PersonController extends Controller
{
    /**
     * Define o disco de armazenamento a ser usado para os uploads temporários.
     */
    private $tempDisk = 'private';

    public function index(Request $request)
    {
        $people = Person::query()
            ->when($request->input('search'), function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('registration_number', 'like', "%{$search}%")
                    ->orWhere('cpf', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn($person) => [
                'id' => $person->id,
                'name' => $person->name,
                'registration_number' => $person->registration_number,
                'cpf' => $person->cpf,
                'bond_type' => $person->bond_type,
            ]);

        return inertia('People/Index', [
            'people' => $people,
            'filters' => $request->only(['search']),
        ]);
    }

    public function edit(Person $person)
    {
        return inertia('People/Edit', [
            'person' => [
                'id' => $person->id,
                'name' => $person->name,
                'registration_number' => $person->registration_number,
                'bond_type' => $person->bond_type,
                'functional_status' => $person->functional_status,
                'cpf' => $person->cpf,
                'rg_number' => $person->rg_number,
                'admission_date' => $person->admission_date ? $person->admission_date->format('Y-m-d') : null,
                'dismissal_date' => $person->dismissal_date ? $person->dismissal_date->format('Y-m-d') : null,
                'current_position' => $person->current_position,
                'current_function' => $person->current_function,
                'allocation_code' => $person->allocation_code,
                'allocation_name' => $person->allocation_name,
            ]
        ]);
    }

    public function update(Request $request, Person $person)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'registration_number' => 'required|string|max:255|unique:people,registration_number,' . $person->id,
            'cpf' => 'required|string|max:14|unique:people,cpf,' . $person->id,
            'bond_type' => 'nullable|string|max:255',
            'functional_status' => 'nullable|string|max:255',
            'rg_number' => 'nullable|string|max:255',
            'admission_date' => 'nullable|date',
            'dismissal_date' => 'nullable|date',
            'current_position' => 'nullable|string|max:255',
            'current_function' => 'nullable|string|max:255',
            'allocation_code' => 'nullable|string|max:255',
            'allocation_name' => 'nullable|string|max:255',
        ]);

        $person->update($validatedData);

        return redirect()->route('persons.index')->with('success', 'Pessoa atualizada com sucesso!');
    }

    /**
     * Etapa 1: Processa o CSV para gerar um preview das mudanças.
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
            $tempFileName = 'person_upload_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $tempFilePath = $file->storeAs('temp_uploads', $tempFileName, $this->tempDisk);
            $absolutePath = Storage::disk($this->tempDisk)->path($tempFilePath);
            $fileHandle = fopen($absolutePath, 'r');

            if (!$fileHandle) throw new \Exception("Não foi possível abrir o arquivo temporário.");

            $rawHeader = fgetcsv($fileHandle, 0, ';');
            if (!$rawHeader) throw new \Exception("Arquivo CSV vazio ou com cabeçalho inválido.");

            $header = array_map('strtoupper', $rawHeader);
            $rowsData = [];
            while (($row = fgetcsv($fileHandle, 0, ';')) !== false) {
                if (empty(array_filter($row))) continue;
                if (count($header) == count($row)) {
                    $rowsData[] = array_combine($header, $row);
                }
            }
            fclose($fileHandle);

            $registrationNumbers = array_column($rowsData, 'MATRICULA');
            $existingPersons = Person::whereIn('registration_number', $registrationNumbers)->get()->keyBy('registration_number');

            $summary = ['new' => 0, 'updated' => 0, 'unchanged' => 0, 'errors' => 0, 'skipped' => 0]; // Contador adicionado
            $errorsList = [];
            $detailedChanges = [];

            foreach ($rowsData as $index => $data) {
                // --- LÓGICA PARA IGNORAR ESTAGIÁRIOS ---
                if (isset($data['REGIME_TRABALHO']) && strtoupper(trim($data['REGIME_TRABALHO'])) === 'ESTAGIARIO') {
                    $summary['skipped']++;
                    continue; // Pula para a próxima linha
                }

                try {
                    $personData = $this->transformData($data);
                    $this->validateRow($personData);

                    $existingPerson = $existingPersons->get($personData['registration_number']);
                    if ($existingPerson) {
                        $diff = $this->comparePersonData($existingPerson, $personData);
                        if (empty($diff)) {
                            $summary['unchanged']++;
                        } else {
                            $summary['updated']++;
                            $detailedChanges[] = ['status' => 'updated', 'name' => $existingPerson->name, 'registration_number' => $existingPerson->registration_number, 'changes' => $diff];
                        }
                    } else {
                        $summary['new']++;
                        $detailedChanges[] = ['status' => 'new', 'name' => $personData['name'], 'registration_number' => $personData['registration_number']];
                    }
                } catch (ValidationException $e) {
                    $summary['errors']++;
                    $errorMessages = Arr::flatten($e->errors());
                    $originalMatricula = $data['MATRICULA'] ?? 'N/A';
                    $errorsList[] = "Erro na linha " . ($index + 2) . " (Matrícula: {$originalMatricula}): " . implode(', ', $errorMessages);
                }
            }

            return response()->json([
                'message' => 'Pré-visualização gerada com sucesso.',
                'summary' => $summary,
                'errors' => $errorsList,
                'detailed_changes' => $detailedChanges,
                'temp_file_path' => $tempFilePath,
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao gerar preview de pessoas: ' . $e->getMessage());
            return response()->json(['message' => 'Ocorreu um erro inesperado ao processar o arquivo para preview.'], 500);
        }
    }

    /**
     * Etapa 2: Confirma e aplica as mudanças do CSV no banco de dados.
     */
    public function confirmUpload(Request $request)
    {
        $validator = Validator::make($request->all(), ['temp_file_path' => 'required|string']);
        if ($validator->fails()) return response()->json(['errors' => $validator->errors()], 422);

        $tempFilePath = $request->input('temp_file_path');
        if (!Storage::disk($this->tempDisk)->exists($tempFilePath) || !str_starts_with($tempFilePath, 'temp_uploads/')) {
            return response()->json(['message' => 'Arquivo temporário inválido ou não encontrado.'], 404);
        }

        $absolutePath = Storage::disk($this->tempDisk)->path($tempFilePath);
        $fileHandle = fopen($absolutePath, 'r');
        $rawHeader = fgetcsv($fileHandle, 0, ';');
        if (!$rawHeader) throw new \Exception("Arquivo CSV vazio ou com cabeçalho inválido.");
        $header = array_map('strtoupper', $rawHeader);
        $processedCount = 0;

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($fileHandle, 0, ';')) !== false) {
                if (empty(array_filter($row)) || count($header) !== count($row)) continue;
                $data = array_combine($header, $row);

                // --- LÓGICA PARA IGNORAR ESTAGIÁRIOS ---
                if (isset($data['REGIME_TRABALHO']) && strtoupper(trim($data['REGIME_TRABALHO'])) === 'ESTAGIARIO') {
                    continue; // Pula para a próxima linha
                }

                try {
                    $personData = $this->transformData($data);
                    $this->validateRow($personData);
                    Person::updateOrCreate(['registration_number' => $personData['registration_number']], $personData);
                    $processedCount++;
                } catch (ValidationException $e) {
                    $originalMatricula = $data['MATRICULA'] ?? 'N/A';
                    Log::warning("Linha ignorada durante confirmação (Matrícula: {$originalMatricula}): " . $e->getMessage());
                    continue;
                }
            }
            DB::commit();
            fclose($fileHandle);
            Storage::disk($this->tempDisk)->delete($tempFilePath);
            return response()->json(['message' => "Operação concluída! {$processedCount} pessoas foram criadas/atualizadas."]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao confirmar upload de pessoas: ' . $e->getMessage() . ' na linha ' . $e->getLine());
            if (isset($tempFilePath) && Storage::disk($this->tempDisk)->exists($tempFilePath)) {
                Storage::disk($this->tempDisk)->delete($tempFilePath);
            }
            return response()->json(['message' => 'Ocorreu um erro inesperado ao salvar os dados. Nenhuma alteração foi salva.'], 500);
        }
    }

    /**
     * Transforma os dados de uma linha do CSV para o formato do banco de dados.
     */
    private function transformData(array $data): array
    {
        $emptyToNull = fn($value) => trim($value) === '' ? null : trim($value);
        return [
            'name' => trim($data['NOME'] ?? ''),
            'registration_number' => $emptyToNull($data['MATRICULA'] ?? null),
            'cpf' => $emptyToNull(preg_replace('/[^0-9]/', '', $data['CPF'] ?? '')),
            'bond_type' => $emptyToNull($data['VINCULO'] ?? null),
            'functional_status' => $emptyToNull($data['SITUACAO'] ?? null),
            'rg_number' => $emptyToNull($data['RG_NUMERO'] ?? null),
            'admission_date' => $this->formatDate($data['ADMISSAO'] ?? null),
            'dismissal_date' => $this->formatDate($data['DEMISSAO'] ?? null),
            'current_position' => $emptyToNull($data['CARGO'] ?? null),
            'current_function' => $emptyToNull($data['FUNCAO'] ?? null),
            'allocation_code' => $emptyToNull($data['LOTACAO'] ?? null),
            'allocation_name' => $emptyToNull($data['NOME_LOT'] ?? null),
        ];
    }

    /**
     * Compara os dados do CSV com uma pessoa existente e retorna as diferenças.
     */
    private function comparePersonData(Person $person, array $newData): array
    {
        $diff = [];
        $personFieldsToCompare = [
            'name', 'bond_type', 'functional_status', 'cpf', 'rg_number',
            'admission_date', 'dismissal_date', 'current_position', 'current_function',
            'allocation_code', 'allocation_name'
        ];
        foreach ($personFieldsToCompare as $field) {
            $personValue = $person->{$field};
            if ($personValue instanceof Carbon) {
                $personValue = $personValue->format('Y-m-d');
            }
            $newValue = $newData[$field];
            if ($personValue != $newValue) {
                $diff[$field] = ['from' => $personValue ?? 'vazio', 'to' => $newValue ?? 'vazio'];
            }
        }
        return $diff;
    }

    /**
     * Valida os dados de uma única linha.
     * @throws ValidationException
     */
    private function validateRow(array $data)
    {
        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
            'registration_number' => 'required|string|max:255',
            'cpf' => 'required|string|digits:11',
            'admission_date' => 'nullable|date',
            'dismissal_date' => 'nullable|date',
        ]);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * Formata data, aceitando vários formatos comuns.
     */
    private function formatDate(?string $dateString): ?string
    {
        $date = trim($dateString ?? '');
        if (empty($date)) return null;
        $formatsToTry = ['d/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y', 'd-m-Y H:i:s', 'd-m-Y H:i', 'd-m-Y', 'Y-m-d H:i:s', 'Y-m-d'];
        foreach ($formatsToTry as $format) {
            try {
                return Carbon::createFromFormat($format, $date)->format('Y-m-d');
            } catch (\Exception $e) {
                continue;
            }
        }
        Log::warning("Falha ao analisar a data: '{$dateString}'. Formato não reconhecido.");
        return null;
    }

    public function cpf()
    {
        return Inertia::render('settings/Cpf', [
            'user' => auth()->user(),
        ]);
    }

    public function cpfUpdate(Request $request)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'cpf' => ['required', 'string', 'digits:11'],
        ]);
        $user->forceFill(['cpf' => $validated['cpf']])->save();
        return Redirect::route('dashboard')->with('success', 'CPF atualizado com sucesso!');
    }
}
