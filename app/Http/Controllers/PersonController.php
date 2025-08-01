<?php

namespace App\Http\Controllers;

use App\Jobs\UpdatePersonManagersJob;
use App\Models\Person;
use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use App\Jobs\PreviewPersonCsvJob;
use App\Models\JobFunction;


class PersonController extends Controller
{
    private $tempDisk = 'private';

    public function index(Request $request)
    {
        $people = Person::query()
            ->with('organizationalUnit')
            ->when($request->input('search'), function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('registration_number', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn($person) => [
                'id' => $person->id,
                'name' => $person->name,
                'registration_number' => $person->registration_number,
                'current_position' => $person->current_position,
                'current_function' => $person->current_function,
                'organizational_unit_id' => $person->organizational_unit_id,
                'organizational_unit' => $person->organizationalUnit ? ['name' => $person->organizationalUnit->name] : null,
            ]);

        $organizationalUnits = OrganizationalUnit::orderBy('name')->get(['id', 'name']);

        return Inertia::render('People/Index', [
            'people' => $people,
            'filters' => $request->only(['search']),
            'organizationalUnits' => $organizationalUnits,
        ]);
    }

    public function create()
    {
        $organizationalUnits = OrganizationalUnit::orderBy('name')->get(['id', 'name']);
        $functionalStatuses = ['ATIVO', 'INATIVO', 'CEDIDO', 'AFASTADO', 'LICENÇA', 'FERIAS', 'EXONERADO', 'APOSENTADO', 'TRABALHANDO'];
        return Inertia::render('People/Edit', [
            'organizationalUnits' => $organizationalUnits,
            'functionalStatuses' => $functionalStatuses,
        ]);
    }

    public function store(Request $request)
    {
        $functionalStatuses = ['ATIVO', 'INATIVO', 'CEDIDO', 'AFASTADO', 'LICENÇA', 'FERIAS', 'EXONERADO', 'APOSENTADO', 'TRABALHANDO'];
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'registration_number' => 'nullable|string|unique:people,registration_number',
            'functional_status' => ['nullable', 'string', Rule::in($functionalStatuses)],
            'organizational_unit_id' => 'nullable|exists:organizational_units,id',
            'cpf' => 'nullable|string|max:14',
            'bond_type' => 'nullable|string|max:255',
            'direct_manager_id' => ['nullable', 'exists:people,id'],
        ]);
        Person::create($validatedData);
        return Redirect::route('people.index')->with('success', 'Pessoa criada com sucesso!');
    }

    public function edit(Person $person)
    {
        $organizationalUnits = OrganizationalUnit::orderBy('name')->get(['id', 'name']);
        $functionalStatuses = [
            'ATIVO',
            'INATIVO',
            'CEDIDO',
            'AFASTADO',
            'LICENÇA',
            'FERIAS',
            'EXONERADO',
            'APOSENTADO',
            'TRABALHANDO'
        ];
        $jobFunctions = JobFunction::orderBy('name')->get(['id', 'name']);

        // Lista de possíveis chefes (ex: quem tem função de chefia)
        $managerOptions = Person::whereNotNull('job_function_id')
            ->orderBy('name')
            ->get(['id', 'name']);

        $subordinates = $person->subordinates()->orderBy('name')->get(['id', 'name', 'registration_number']);

        return Inertia::render('People/Edit', [
            'person' => [
                'id' => $person->id,
                'name' => $person->name,
                'registration_number' => $person->registration_number,
                'bond_type' => $person->bond_type,
                'functional_status' => $person->functional_status,
                'cpf' => $person->cpf,
                'rg_number' => $person->rg_number,
                'admission_date' => $person->admission_date?->format('Y-m-d'),
                'dismissal_date' => $person->dismissal_date?->format('Y-m-d'),
                'current_position' => $person->current_position,
                'job_function_id' => $person->job_function_id,
                'organizational_unit_id' => $person->organizational_unit_id,
                'direct_manager_id' => $person->direct_manager_id, // ✅ novo
            ],
            'organizationalUnits' => $organizationalUnits,
            'functionalStatuses' => $functionalStatuses,
            'jobFunctions' => $jobFunctions,
            'managerOptions' => $managerOptions, // ✅ novo
            'subordinates' => $subordinates,
        ]);
    }

    public function update(Request $request, Person $person)
    {
        $functionalStatuses = ['ATIVO', 'INATIVO', 'CEDIDO', 'AFASTADO', 'LICENÇA', 'FERIAS', 'EXONERADO', 'APOSENTADO', 'TRABALHANDO'];

        // Define se é um vínculo manual
        $isManual = $request->input('bond_type') === 'manual';

        $validatedData = $request->validate([
            'name' => 'sometimes|required|string|max:255',

            'registration_number' => array_filter([
                $isManual ? 'nullable' : 'required',
                'string',
                'max:255',
                Rule::unique('people', 'registration_number')->ignore($person->id),
            ]),

            'cpf' => [
                'sometimes',
                'required',
                'string',
                'max:14',
                Rule::unique('people', 'cpf')->ignore($person->id),
            ],

            'functional_status' => ['nullable', 'string', Rule::in($functionalStatuses)],
            'organizational_unit_id' => 'nullable|exists:organizational_units,id',
            'bond_type' => 'nullable|string|max:255',
            'rg_number' => 'nullable|string|max:255',
            'admission_date' => 'nullable|date',
            'dismissal_date' => 'nullable|date',
            'direct_manager_id' => ['nullable', 'exists:people,id'],
            'job_function_id' => 'nullable|exists:job_functions,id',
        ]);

        $person->update($validatedData);

        return Redirect::route('people.index')->with('success', 'Pessoa atualizada com sucesso!');
    }


    public function destroy(Person $person)
    {
        $person->delete();
        return Redirect::route('people.index')->with('success', 'Pessoa excluída com sucesso!');
    }

    /**
     * Etapa 1: Processa o CSV para gerar um preview das mudanças.
     */
    public function previewUpload(Request $request)
    {
        $validator = Validator::make($request->all(), ['file' => 'required|file|mimes:csv,txt|max:2048']);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $file = $request->file('file');
            $tempFileName = 'person_upload_' . uniqid() . '.' . $file->getClientOriginalExtension();

            // Garanta que o disco está igual ao do Job
            $disk = $this->tempDisk ?? 'local';
            $tempFilePath = $file->storeAs('temp_uploads', $tempFileName, $disk);

            // Chame o Job diretamente, SINCRONAMENTE
            $job = new PreviewPersonCsvJob($tempFilePath, $disk);
            $result = $job->handle();

            return response()->json($result);

        } catch (\Exception $e) {
            // \Log::error('Erro no previewUpload', ['exception' => $e]);
            // Exibir o erro real para debug (remova em produção)
            return response()->json([
                'message' => 'Erro inesperado no preview.',
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }




    /**
     * Etapa 2: Confirma e aplica as mudanças do CSV no banco de dados.
     */

    public function confirmUpload(Request $request)
    {
        $validator = Validator::make($request->all(), ['temp_file_path' => 'required|string']);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $tempFilePath = $request->input('temp_file_path');
        if (!Storage::disk($this->tempDisk)->exists($tempFilePath)) {
            return response()->json(['message' => 'Arquivo temporário não encontrado.'], 404);
        }

        DB::beginTransaction();
        $successCount = 0;
        $skippedCount = 0;
        $errorCount = 0;
        $errorsList = [];
        try {
            $rowsData = $this->getCsvRowsAsMap($tempFilePath);

            // Cria/atualiza unidades organizacionais
            $this->createOrUpdateUnitsFromCsv($rowsData);
            $this->resolveUnitHierarchy();

            $organizationalUnitsLookup = OrganizationalUnit::all()->keyBy('code');

            foreach ($rowsData as $index => $data) {
                if ($this->shouldSkipRow($data)) {
                    $skippedCount++;
                    // \Log::info('Linha pulada no confirmUpload:', $data);
                    continue;
                }
                try {
                    $personData = $this->transformPersonData($data, $organizationalUnitsLookup);
                    $this->validateRow($personData);

                    if (!$personData['name'] || !$personData['registration_number']) {
                        $errorCount++;
                        $errorsList[] = "Linha " . ($index + 2) . ": Nome ou matrícula em branco.";
                        // \Log::warning('Pessoa ignorada por falta de dados obrigatórios', $personData);
                        continue;
                    }

                    Person::updateOrCreate(
                        ['registration_number' => $personData['registration_number']],
                        $personData
                    );
                    // \Log::info('Pessoa criada/atualizada:', $personData);
                    $successCount++;
                } catch (\Exception $e) {
                    $errorCount++;
                    $errorsList[] = "Linha " . ($index + 2) . ": " . $e->getMessage();
                    // \Log::error('Erro ao importar pessoa:', ['linha' => $index + 2, 'error' => $e->getMessage(), 'dados' => $data]);
                }
            }

            DB::commit();
            Storage::disk($this->tempDisk)->delete($tempFilePath);

            // Dispara o Job para atualizar chefias em background
            UpdatePersonManagersJob::dispatch();

            return response()->json([
                'message' => "Operação concluída! $successCount pessoas foram criadas/atualizadas. ($skippedCount puladas, $errorCount com erro). A atualização das chefias será processada em segundo plano.",
                'errors' => $errorsList,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            if (isset($tempFilePath) && Storage::disk($this->tempDisk)->exists($tempFilePath)) {
                Storage::disk($this->tempDisk)->delete($tempFilePath);
            }
            // \Log::error('Erro inesperado ao salvar no confirmUpload', ['exception' => $e]);
            return response()->json(['message' => 'Erro inesperado ao salvar. Nenhuma alteração foi feita.', 'exception' => $e->getMessage()], 500);
        }
    }



    // --- MÉTODOS AUXILIARES DA LÓGICA DE UPLOAD ---

    private function getCsvRowsAsMap(string $tempFilePath): array
    {
        $absolutePath = Storage::disk($this->tempDisk)->path($tempFilePath);
        $fileHandle = fopen($absolutePath, 'r');
        $rawHeader = fgetcsv($fileHandle, 0, ';');
        if (!$rawHeader)
            return [];
        $header = array_map('strtoupper', $rawHeader);
        $rowsData = [];
        while (($row = fgetcsv($fileHandle, 0, ';')) !== false) {
            if (count($header) == count($row)) {
                $rowsData[] = array_combine($header, $row);
            }
        }
        fclose($fileHandle);
        return $rowsData;
    }

    private function createOrUpdateUnitsFromCsv(array $rowsData): void
    {
        $uniqueUnits = collect($rowsData)->unique('LOTACAO')->filter(fn($row) => !empty($row['LOTACAO'] ?? null) && !empty($row['NOME_LOT'] ?? null));

        foreach ($uniqueUnits as $data) {
            $code = trim($data['LOTACAO']);
            $name = trim($data['NOME_LOT']);
            $type = $this->getOrganizationalUnitType($name);

            OrganizationalUnit::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'type' => $type, 'parent_id' => null]
            );
        }
    }

    private function resolveUnitHierarchy(): void
    {

        // A ordenação por código é CRUCIAL para esta lógica funcionar.
        $units = OrganizationalUnit::query()->orderBy('code', 'asc')->get();
        $unitsByCode = $units->keyBy('code');

        // Limpa todos os parent_id para garantir um recomeço limpo.
        foreach ($units as $unit) {
            if ($unit->parent_id !== null) {
                $unit->parent_id = null;
                $unit->save();
            }
        }

        // Variáveis de estado para guardar o último pai visto em cada nível.
        $lastSecretariaId = null;
        $lastDiretoriaId = null;
        $lastCoordinacaoId = null;

        foreach ($units as $unit) {

            // --- REGRA ESPECIAL PARA UNIDADES DE APOIO ---
            // Se o nome contém "APOIO AO GABINETE", ele é ligado à Secretaria do seu bloco e a lógica para.
            if (str_contains(strtoupper($unit->name), 'APOIO AO GABINETE')) {
                $secretariaCode = substr($unit->code, 0, 4) . '0101';
                $secretaria = $unitsByCode->get($secretariaCode);

                if ($secretaria) {
                    $unit->parent_id = $secretaria->id;
                }

                if ($unit->isDirty('parent_id')) {
                    $unit->save();
                }
                continue; // Pula para a próxima unidade
            }

            // --- LÓGICA HIERÁRQUICA PADRÃO ---
            switch ($unit->type) {
                case 'Secretaria':
                    // É um nó raiz. Define-se como o pai para o próximo nível e reseta os inferiores.
                    $lastSecretariaId = $unit->id;
                    $lastDiretoriaId = null;
                    $lastCoordinacaoId = null;
                    break;

                case 'Diretoria':
                    // O pai é a última Secretaria vista.
                    $unit->parent_id = $lastSecretariaId;

                    // Define-se como o pai para o próximo nível e reseta o inferior.
                    $lastDiretoriaId = $unit->id;
                    $lastCoordinacaoId = null;
                    break;

                case 'Coordenação':
                    // O pai é a última Diretoria vista.
                    // Se não houver Diretoria, faz fallback para a Secretaria.
                    $unit->parent_id = $lastDiretoriaId ?? $lastSecretariaId;

                    // Define-se como o pai para o próximo nível.
                    $lastCoordinacaoId = $unit->id;
                    break;

                case 'Departamento':
                case 'Outro':
                    // O pai é a última Coordenação vista.
                    // Se não houver Coordenação, faz fallback para a Diretoria, e depois para a Secretaria.
                    $unit->parent_id = $lastCoordinacaoId ?? $lastDiretoriaId ?? $lastSecretariaId;
                    break;
            }

            // Salva a alteração do parent_id se houver alguma.
            if ($unit->isDirty('parent_id')) {
                $unit->save();
            }
        }

    }

    private function getOrganizationalUnitType(string $nameLot): string
    {
        $nameLotUpper = strtoupper(trim($nameLot));
        if (str_contains($nameLotUpper, 'SECRETARIA'))
            return 'Secretaria';
        if (str_contains($nameLotUpper, 'DIRETORIA'))
            return 'Diretoria';
        if (str_contains($nameLotUpper, 'COORDENACAO') || str_contains($nameLotUpper, 'COORDENAÇÃO'))
            return 'Coordenação';
        if (str_contains($nameLotUpper, 'DEPARTAMENTO') || str_contains($nameLotUpper, 'NUCLEO'))
            return 'Departamento';
        return 'Outro';
    }

    private function shouldSkipRow(array $data): bool
    {
        $regime = strtoupper(trim($data['REGIME_TRABALHO'] ?? ''));
        $situacao = strtoupper(trim($data['SITUACAO'] ?? ''));
        $pular = $regime === 'ESTAGIARIO' || $situacao === 'CESSADO';
        if ($pular) {
            // \Log::info('PULANDO REGISTRO:', ['nome' => $data['NOME'] ?? '', 'matricula' => $data['MATRICULA'] ?? '', 'regime' => $regime, 'situacao' => $situacao]);
        }
        return $pular;
    }


    private function transformPersonData(array $data, \Illuminate\Support\Collection $organizationalUnitsLookup): array
    {
        $emptyToNull = fn($value) => trim($value) === '' ? null : trim($value);
        $lotacaoCode = $emptyToNull($data['LOTACAO'] ?? null);
        $organizationalUnitId = null;
        if ($lotacaoCode && $organizationalUnitsLookup->has($lotacaoCode)) {
            $organizationalUnitId = $organizationalUnitsLookup->get($lotacaoCode)->id;
        }

        $status = trim($data['SITUACAO'] ?? '');

        // --- Busca/cria função ---
        $functionRaw = $emptyToNull($data['FUNCAO'] ?? null);

        $functionCode = null;
        $functionName = null;

        if ($functionRaw && strpos($functionRaw, '-') !== false) {
            [$functionCode, $functionName] = array_map('trim', explode('-', $functionRaw, 2));
        } elseif ($functionRaw) {
            $functionName = $functionRaw;
        }

        $jobFunctionId = null;
        if ($functionCode) {
            $jobFunction = \App\Models\JobFunction::firstOrCreate(
                ['code' => $functionCode], // só pelo código
                [
                    'name' => $functionName,
                    'type' => 'servidor',      // ou ajuste conforme sua regra
                    'is_manager' => false      // ou ajuste conforme sua regra
                ]
            );
            $jobFunctionId = $jobFunction->id;
        }


        return [
            'name' => trim($data['NOME'] ?? ''),
            'registration_number' => $emptyToNull($data['MATRICULA'] ?? null),
            'cpf' => $emptyToNull(str_pad(preg_replace('/[^0-9]/', '', $data['CPF'] ?? ''), 11, '0', STR_PAD_LEFT)),
            'bond_type' => $emptyToNull($data['VINCULO'] ?? null),
            'functional_status' => $status === '' ? null : strtoupper($status),
            'rg_number' => $emptyToNull($data['RG_NUMERO'] ?? null),
            'admission_date' => $this->formatDate($data['ADMISSAO'] ?? null),
            'dismissal_date' => $this->formatDate($data['DEMISSAO'] ?? null),
            'current_position' => $emptyToNull($data['CARGO'] ?? null),
            'organizational_unit_id' => $organizationalUnitId,
            'job_function_id' => $jobFunctionId,
            'sala' => $data['SALA'] ?? null,
            'descricao_sala' => $data['DESCRICAO_SALA'] ?? null,
        ];
    }


    private function comparePersonData(Person $person, array $newData): array
    {
        $diff = [];
        $fieldsToCompare = [
            'name',
            'bond_type',
            'functional_status',
            'cpf',
            'rg_number',
            'admission_date',
            'dismissal_date',
            'current_position',
            'current_function',
            'organizational_unit_id'
        ];
        foreach ($fieldsToCompare as $field) {
            $personValue = $person->{$field};
            if ($personValue instanceof Carbon) {
                $personValue = $personValue->format('Y-m-d');
            }
            $newValue = $newData[$field] ?? null;
            if (($personValue === null && $newValue === '') || ($personValue === '' && $newValue === null)) {
                continue;
            }
            if ($personValue != $newValue) {
                $diff[$field] = ['from' => $personValue ?? 'vazio', 'to' => $newValue ?? 'vazio'];
            }
        }
        return $diff;
    }

    private function validateRow(array $data)
    {
        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
            'registration_number' => 'required|string|max:255',
            'cpf' => 'nullable|string|digits_between:10,11',
            'admission_date' => 'nullable|date',
            'dismissal_date' => 'nullable|date',
            'organizational_unit_id' => 'nullable|exists:organizational_units,id',
        ]);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    private function formatDate(?string $dateString): ?string
    {
        $date = trim($dateString ?? '');
        if (empty($date)) {
            return null;
        }

        // Adicionados os formatos com hífen para maior flexibilidade
        $formatsToTry = [
            'd/m/Y',        // Formato com barra
            'd-m-Y',        // <-- NOVO FORMATO ADICIONADO
            'Y-m-d',        // Formato ISO (padrão)
            'd/m/Y H:i:s',
            'd/m/Y H:i',
            'd-m-Y H:i:s',  // <-- NOVO FORMATO ADICIONADO
            'd-m-Y H:i',    // <-- NOVO FORMATO ADICIONADO
            'Y-m-d H:i:s',
        ];

        foreach ($formatsToTry as $format) {
            try {
                // Tenta criar uma data a partir do formato
                return Carbon::createFromFormat($format, $date)->format('Y-m-d');
            } catch (\Exception $e) {
                // Se falhar, continua para o próximo formato
                continue;
            }
        }

        // Se nenhum formato funcionar, retorna nulo e registra um aviso
        return null;
    }

    /**
     * Display the CPF settings page for the authenticated user.
     */
    public function cpf()
    {
        return Inertia::render('settings/Cpf', [
            'user' => Auth::user(),
        ]);
    }

    /**
     * Update the CPF for the authenticated user.
     */
    public function cpfUpdate(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'cpf' => ['required', 'string', 'digits:11'],
        ]);

        $user->forceFill(['cpf' => $validated['cpf']])->save();

        // Atribui a role 'Servidor' ao usuário (se ainda não tiver)
        $role = Role::firstOrCreate(['name' => 'Servidor'], ['level' => 1]);
        if (!$user->roles()->where('name', 'Servidor')->exists()) {
            $user->roles()->attach($role->id);
        }

        return Redirect::route('dashboard')->with('success', 'CPF atualizado com sucesso!');
    }

    public function storeManual(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'cpf' => 'required|string',
        ]);

        // Remove máscara do CPF
        $cpf = preg_replace('/\D/', '', $data['cpf']);

        // Verifica se já existe uma pessoa com esse CPF
        if (Person::where('cpf', $cpf)->exists()) {
            throw ValidationException::withMessages([
                'cpf' => 'Já existe uma pessoa cadastrada com este CPF.',
            ]);
        }

        // Verifica se já existe um usuário com esse CPF (segurança extra)
        if (User::where('cpf', $cpf)->exists()) {
            throw ValidationException::withMessages([
                'cpf' => 'Já existe um usuário com este CPF.',
            ]);
        }

        // Cria o usuário com senha padrão
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'username' => $cpf,
            'cpf' => $cpf,
            'password' => Hash::make('Abc@1234'),
            'must_change_password' => true,
        ]);

        // Cria a pessoa manual e associa com o user
        $person = Person::create([
            'name' => $data['name'],
            'cpf' => $cpf,
            'bond_type' => 'manual',
            'user_id' => $user->id,
        ]);

        return redirect()->route('configs')->with('success', 'Pessoa manual criada com sucesso.');
    }

    public function createManual()
    {
        return Inertia::render('People/CreateManual');
    }
}