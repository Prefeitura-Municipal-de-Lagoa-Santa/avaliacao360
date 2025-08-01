<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Person extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'registration_number',
        'bond_type',
        'functional_status',
        'cpf',
        'rg_number',
        'admission_date',
        'dismissal_date',
        'current_position',
        'organizational_unit_id',
        'user_id',
        'direct_manager_id',
        'sala',
        'descricao_sala',
        'job_function_id',
    ];

    protected $casts = [
        'admission_date' => 'date',
        'dismissal_date' => 'date',
    ];

    /**
     * Get the organizational unit that owns the Person.
     */
    public function organizationalUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class, 'organizational_unit_id');
    }

    /**
     * Get the user that owns the Person (if applicable).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Escopo para obter apenas pessoas elegíveis para avaliação.
     */
    public function scopeEligibleForEvaluation(Builder $query): void
    {
        $probationaryCutoffDate = Carbon::now()->subYears(3);

        $query->whereIn('functional_status', ['TRABALHANDO', 'FERIAS'])
            ->where(function (Builder $subQuery) use ($probationaryCutoffDate) {
                // 0. Exclui "3 - Concursado" sem função
                $subQuery->where(function (Builder $q) {
                    $q->where('bond_type', '!=', '3 - Concursado')
                        ->orWhereNotNull('job_function_id');
                })
                    // 1. O tipo de vínculo NÃO ESTÁ na lista de probatório
                    ->where(function (Builder $q) use ($probationaryCutoffDate) {
                    $q->whereNotIn('bond_type', ['1 - Efetivo', '8 - Concursado'])
                        // OU 2. Já passou do período probatório de 3 anos.
                        ->orWhere('admission_date', '<=', $probationaryCutoffDate)
                        // OU 3. TEM uma função de chefia (mesmo que esteja em probatório)
                        ->orWhere(function (Builder $bossQuery) {
                            $bossQuery->whereNotNull('job_function_id');
                        });
                });
            });
    }



    /**
     * Verifica se uma instância específica de Person é elegível para avaliação.
     * Retorna true se a pessoa deve ser avaliada, false caso contrário.
     */
    public function isEligibleForEvaluation(): bool
    {
        // Regra 1: Precisa estar 'TRABALHANDO' para ser elegível.
        if ($this->functional_status !== 'TRABALHANDO') {
            return false;
        }

        // Regra 2: Se tem uma função de chefia, é sempre elegível.
        if (!is_null($this->current_function) || $this->current_position === '380-SECRETARIO MUNICIPAL') {
            return true;
        }

        // Regra 3: Verifica o estágio probatório.
        $probationaryCutoffDate = Carbon::now()->subYears(3);
        $isProbationaryBond = in_array($this->bond_type, ['1 - Efetivo', '8 - Concursado']);
        $isWithin3Years = $this->admission_date > $probationaryCutoffDate;

        // Se está em estágio probatório E não tem função (já checado acima), não é elegível.
        if ($isProbationaryBond && $isWithin3Years) {
            return false;
        }

        // Se passou por todas as verificações, é elegível.
        return true;
    }

    // Relacionamento com o chefe direto
    public function directManager()
    {
        return $this->belongsTo(Person::class, 'direct_manager_id');
    }

    // Relacionamento para listar todos os subordinados dessa pessoa
    public function subordinates()
    {
        return $this->hasMany(Person::class, 'direct_manager_id');
    }

    public function jobFunction()
    {
        return $this->belongsTo(JobFunction::class, 'job_function_id');
    }

    public function acknowledgments()
    {
        return $this->hasMany(Acknowledgment::class);
    }


}
