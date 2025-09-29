<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Evaluation extends Model
{
    use HasFactory, LogsActivity;

    // Apenas os campos que a avaliação representa
    protected $fillable = [
        'type',
        'form_id',
        'evaluated_person_id',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function evaluated(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'evaluated_person_id');
    }

    // O relacionamento com o avaliador foi removido daqui
    // e agora vive apenas em EvaluationRequest

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }

    public function evaluationRequests(): HasMany
    {
        return $this->hasMany(EvaluationRequest::class);
    }

    public function evaluatedPerson()
    {
        return $this->belongsTo(Person::class, 'evaluated_person_id');
    }

    /**
     * Customizar as informações do log de atividade
     */
    public function customizeActivityLog($logData, $action)
    {
        // Adicionar informações específicas da avaliação
        $description = $logData['description'];
        
        switch ($action) {
            case 'created':
                $description = "Nova avaliação criada para {$this->evaluatedPerson?->name} (Tipo: {$this->type})";
                break;
            case 'updated':
                $description = "Avaliação de {$this->evaluatedPerson?->name} foi atualizada";
                break;
            case 'deleted':
                $description = "Avaliação de {$this->evaluatedPerson?->name} foi excluída";
                break;
        }

        $logData['description'] = $description;
        
        return $logData;
    }

    /**
     * Determinar se deve registrar a atividade
     */
    public function shouldLogActivity($action)
    {
        // Por exemplo, talvez não queremos logar certas atualizações automáticas
        return true;
    }
}