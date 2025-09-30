<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Models\Person;
use App\Models\OrganizationalUnit;
use Illuminate\Support\Facades\Validator;

class PreviewPersonCsvJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $filePath;
    public $tempDisk;

    public function __construct($filePath, $tempDisk = 'local')
    {
        $this->filePath = $filePath;
        $this->tempDisk = $tempDisk;
    }

    public function handle()
    {
        $rowsData = $this->getCsvRowsAsMap($this->filePath);

        $this->createOrUpdateUnitsFromCsv($rowsData);
        $this->resolveUnitHierarchy();

        $organizationalUnitsLookup = OrganizationalUnit::all()->keyBy('code');
        $registrationNumbers = array_column($rowsData, 'MATRICULA');
        $existingPersons = Person::whereIn('registration_number', $registrationNumbers)->get()->keyBy('registration_number');

        $summary = ['new' => 0, 'updated' => 0, 'unchanged' => 0, 'errors' => 0, 'skipped' => 0, 'to_inactivate' => 0];
        $errorsList = [];
        $detailedChanges = [];
        $registrationNumbersInCsv = [];

        foreach ($rowsData as $index => $data) {
            if ($this->shouldSkipRow($data)) {
                $summary['skipped']++;
                continue;
            }
            try {
                $personData = $this->transformPersonData($data, $organizationalUnitsLookup);
                $this->validateRow($personData);

                // Adiciona a matrícula na lista de pessoas da planilha
                if ($personData['registration_number']) {
                    $registrationNumbersInCsv[] = $personData['registration_number'];
                }

                $existingPerson = $existingPersons->get($personData['registration_number']);
                if ($existingPerson) {
                    $diff = $this->comparePersonData($existingPerson, $personData);
                    if (empty($diff)) {
                        $summary['unchanged']++;
                    } else {
                        $summary['updated']++;
                        $detailedChanges[] = [
                            'status' => 'updated',
                            'name' => $existingPerson->name,
                            'registration_number' => $existingPerson->registration_number,
                            'changes' => $diff
                        ];
                    }
                } else {
                    $summary['new']++;
                    $detailedChanges[] = [
                        'status' => 'new',
                        'name' => $personData['name'],
                        'registration_number' => $personData['registration_number']
                    ];
                }
            } catch (\Exception $e) {
                $summary['errors']++;
                $errorsList[] = 'Linha ' . ($index + 2) . ': ' . $e->getMessage();
            }
        }

        // Calcula quantas pessoas serão inativadas
        $peopleToInactivate = Person::whereNotIn('registration_number', $registrationNumbersInCsv)
            ->whereNotIn('functional_status', ['INATIVO', 'EXONERADO', 'APOSENTADO'])
            ->whereNotNull('registration_number')
            ->get();

        $summary['to_inactivate'] = $peopleToInactivate->count();

        // Adiciona informações das pessoas que serão inativadas
        $inactivationDetails = [];
        foreach ($peopleToInactivate as $person) {
            $inactivationDetails[] = [
                'status' => 'to_inactivate',
                'name' => $person->name,
                'registration_number' => $person->registration_number,
                'current_status' => $person->functional_status
            ];
        }

        return [
            'message' => 'Pré-visualização gerada com sucesso.',
            'summary' => $summary,
            'errors' => $errorsList,
            'detailed_changes' => $detailedChanges,
            'inactivation_details' => $inactivationDetails,
            'temp_file_path' => $this->filePath,
        ];
    }

    public function getCsvRowsAsMap($filePath)
    {
        $file = \Storage::disk($this->tempDisk)->path($filePath);

        if (!file_exists($file)) {
            throw new \Exception("Arquivo CSV não encontrado: $file");
        }

        $csv = array_map(function ($linha) {
            return str_getcsv($linha, ';');
        }, file($file));

        $header = array_shift($csv);
        $data = [];
        foreach ($csv as $i => $row) {
            if (count($row) !== count($header)) {
                throw new \Exception("Erro no CSV na linha " . ($i + 2) . ": esperado " . count($header) . " colunas, encontrado " . count($row) . " colunas. Corrija o arquivo e tente novamente.");
            }
            $data[] = array_combine($header, $row);
        }
        return $data;
    }



    public function createOrUpdateUnitsFromCsv($rowsData)
    {
    }

    public function resolveUnitHierarchy()
    {
    }

    private function shouldSkipRow(array $data): bool
    {
        $regime = strtoupper(trim($data['REGIME_TRABALHO'] ?? ''));
        $situacao = strtoupper(trim($data['SITUACAO'] ?? ''));
        $pular = $regime === 'ESTAGIARIO' || $situacao === 'CESSADO';
        if ($pular) {
        }
        return $pular;
    }


    private function isEstagiario($valor)
    {
        // Remove acentos e compara ignorando caixa
        $normalizado = strtr(
            mb_strtolower(trim($valor)),
            [
                'á' => 'a',
                'ã' => 'a',
                'â' => 'a',
                'à' => 'a',
                'é' => 'e',
                'ê' => 'e',
                'í' => 'i',
                'ó' => 'o',
                'õ' => 'o',
                'ô' => 'o',
                'ú' => 'u',
                'ç' => 'c'
            ]
        );
        return $normalizado === 'estagiario';
    }


    public function validateRow($personData)
    {
        Validator::make($personData, [
            'name' => 'required|string',
            'registration_number' => 'nullable|string',
        ])->validate();
    }

    public function comparePersonData($existingPerson, $personData)
    {
        $diff = [];
        foreach ($personData as $key => $value) {
            if (isset($existingPerson->$key) && $existingPerson->$key != $value) {
                $diff[$key] = [
                    'old' => $existingPerson->$key,
                    'new' => $value,
                ];
            }
        }
        return $diff;
    }

    public function transformPersonData($data, $organizationalUnitsLookup = null)
    {
        return [
            'name' => $data['NOME'] ?? null,
            'registration_number' => $data['MATRICULA'] ?? null,
            'bond_type' => $data['VINCULO'] ?? null,
            'functional_status' => $data['SITUACAO'] ?? null,
            'cpf' => $data['CPF'] ?? null,
            'rg_number' => $data['RG_NUMERO'] ?? null,
            'admission_date' => $data['DATA_ADMISSAO'] ?? null,
            'dismissal_date' => $data['DATA_DEMISSAO'] ?? null,
            'current_position' => $data['CARGO_ATUAL'] ?? null,
            'job_function_id' => $data['JOB_FUNCTION_ID'] ?? null,
            'organizational_unit_id' => $organizationalUnitsLookup[$data['UNIDADE_CODIGO'] ?? '']?->id ?? null,
            'user_id' => $data['USER_ID'] ?? null,
            'sala' => $data['SALA'] ?? null,
            'descricao_sala' => $data['DESCRICAO_SALA'] ?? null,
        ];
    }
}
