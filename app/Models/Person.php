<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Person extends Model
{
    use HasFactory, LogsActivity;

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
        'admission_date' => 'datetime',
        'dismissal_date' => 'datetime',
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

    public function assignedRecourses()
    {
        return $this->belongsToMany(EvaluationRecourse::class, 'evaluation_recourse_assignees', 'person_id', 'recourse_id')
                    ->withTimestamps()
                    ->withPivot('assigned_by', 'assigned_at');
    }

    /**
     * Escopo para obter apenas pessoas elegíveis para avaliação.
     */
    public function scopeEligibleForEvaluation(Builder $query): void
    {
        $query->where(function (Builder $mainQuery) {
            // Pessoas em status normal de trabalho (incluindo CEDIDO como TRABALHANDO)
            $mainQuery->whereIn('functional_status', ['TRABALHANDO', 'FERIAS', 'CEDIDO'])
                ->where(function (Builder $subQuery) {
                    // Exclui apenas "3 - Concursado" sem função
                    $subQuery->where(function (Builder $q) {
                        $q->where('bond_type', '!=', '3 - Concursado')
                            ->orWhereNotNull('job_function_id');
                    });
                    // NOVA REGRA: Pessoas em estágio probatório são tratadas igualmente
                    // (removida a exclusão do período probatório)
                })
                // OU pessoas AFASTADAS que tenham função (chefes podem ser avaliados pelos subordinados mesmo afastados)
                ->orWhere(function (Builder $afastadoQuery) {
                    $afastadoQuery->where('functional_status', 'AFASTADO')
                        ->whereNotNull('job_function_id')
                        ->where(function (Builder $q) {
                            $q->where('bond_type', '!=', '3 - Concursado')
                                ->orWhereNotNull('job_function_id');
                        });
                    // NOVA REGRA: Pessoas AFASTADAS em estágio probatório também são elegíveis se têm função
                });
        });
    }

    /**
     * Verifica se uma instância específica de Person é elegível para avaliação.
     * Retorna true se a pessoa deve ser avaliada, false caso contrário.
     */
    public function isEligibleForEvaluation(): bool
    {
        // Regra 1: Precisa estar em status válido (CEDIDO tratado como TRABALHANDO)
        if (!in_array($this->functional_status, ['TRABALHANDO', 'FERIAS', 'CEDIDO', 'AFASTADO'])) {
            return false;
        }

        // Regra 2: Se está AFASTADO ou em FERIAS, só é elegível se tiver função de chefia
        if (in_array($this->functional_status, ['AFASTADO', 'FERIAS']) && is_null($this->job_function_id)) {
            return false;
        }

        // Regra 3: Exclui "3 - Concursado" sem função
        if ($this->bond_type === '3 - Concursado' && is_null($this->job_function_id)) {
            return false;
        }

        // Regra 4: Se tem uma função de chefia, é sempre elegível.
        if (!is_null($this->job_function_id) || $this->current_position === '380-SECRETARIO MUNICIPAL') {
            return true;
        }

        // Regra 5: NOVA LÓGICA - Pessoas em estágio probatório são tratadas igualmente
        // (removida a exclusão do período probatório)
        
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
