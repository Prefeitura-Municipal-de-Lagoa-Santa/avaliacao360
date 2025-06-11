<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class ProcessUserUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $tempFilePath;

    /**
     * Cria uma nova instância do job.
     *
     * @param string $tempFilePath O caminho para o arquivo CSV temporário.
     */
    public function __construct(string $tempFilePath)
    {
        $this->tempFilePath = $tempFilePath;
    }

    /**
     * Executa o job.
     */
    public function handle(): void
    {
        Log::info("Iniciando job de processamento de usuários para o arquivo: {$this->tempFilePath}");

        try {
            if (!Storage::disk('local')->exists($this->tempFilePath)) {
                Log::error("Arquivo {$this->tempFilePath} não encontrado para processamento no job.");
                return;
            }

            $absolutePath = Storage::disk('local')->path($this->tempFilePath);
            $fileHandle = fopen($absolutePath, 'r');
            $header = fgetcsv($fileHandle, 0, ';');
            $processedCount = 0;

            while (($row = fgetcsv($fileHandle, 0, ';')) !== false) {
                 if (count($header) !== count($row)) continue;

                 $data = array_combine($header, $row);
                 $userData = $this->transformData($data);

                 if (isset($userData['bond_type']) && strcasecmp($userData['bond_type'], 'ESTAGIARIO') == 0) {
                     continue;
                 }

                 try {
                     $this->validateRow($userData, 0);

                     // --- LOG ADICIONADO AQUI ---
                     // Registra os dados que serão usados para criar/atualizar o usuário.
                     Log::info('Processando usuário:', $userData);
                     // --- FIM DO LOG ---

                     User::updateOrCreate(
                         ['registration_number' => $userData['registration_number']],
                         $userData
                     );
                     $processedCount++;
                 } catch (ValidationException $e) {
                     Log::warning("Linha ignorada no job para a matrícula {$userData['registration_number']} devido a erro de validação: " . implode(', ', \Illuminate\Support\Arr::flatten($e->errors())));
                     continue;
                 }
            }
            fclose($fileHandle);
            Storage::disk('local')->delete($this->tempFilePath);

            Log::info("Job de processamento de usuários concluído. {$processedCount} usuários processados para o arquivo: {$this->tempFilePath}");

        } catch (\Exception $e) {
             Log::error('Erro crítico no job ProcessUserUpload: ' . $e->getMessage());
             if (Storage::disk('local')->exists($this->tempFilePath)) {
                 Storage::disk('local')->delete($this->tempFilePath);
             }
             throw $e;
        }
    }

    // Métodos de ajuda (copiados do controller para auto-suficiência do job)

    private function transformData(array $data): array
    {
        $cpf = preg_replace('/[^0-9]/', '', $data['CPF'] ?? '');
        $transformed = [
            'name' => trim($data['NOME'] ?? ''),
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


        return $transformed;
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
}
