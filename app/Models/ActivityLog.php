<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'model_type',
        'model_id',
        'action',
        'user_id',
        'user_name',
        'ip_address',
        'user_agent',
        'old_values',
        'new_values',
        'changes',
        'description',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'changes' => 'array',
    ];

    /**
     * Relacionamento polimórfico com o modelo que foi alterado
     */
    public function model(): MorphTo
    {
        return $this->morphTo('model', 'model_type', 'model_id');
    }

    /**
     * Relacionamento com o usuário que fez a ação
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Escopo para filtrar por modelo
     */
    public function scopeForModel($query, $modelType, $modelId = null)
    {
        $query = $query->where('model_type', $modelType);
        
        if ($modelId) {
            $query->where('model_id', $modelId);
        }
        
        return $query;
    }

    /**
     * Escopo para filtrar por ação
     */
    public function scopeAction($query, $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Escopo para filtrar por usuário
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Formatar a descrição da ação
     */
    public function getFormattedDescriptionAttribute(): string
    {
        $modelName = class_basename($this->model_type);
        $userName = $this->user_name ?? 'Sistema';
        
        switch ($this->action) {
            case 'created':
                return "{$userName} criou um novo {$modelName} (ID: {$this->model_id})";
            case 'updated':
                return "{$userName} atualizou {$modelName} (ID: {$this->model_id})";
            case 'deleted':
                return "{$userName} excluiu {$modelName} (ID: {$this->model_id})";
            default:
                return $this->description ?? "{$userName} executou ação {$this->action} em {$modelName}";
        }
    }

    /**
     * Obter lista de campos que foram alterados de forma legível
     */
    public function getChangedFieldsAttribute(): array
    {
        if (!$this->changes) {
            return [];
        }

        $formatted = [];
        foreach ($this->changes as $field => $values) {
            $formatted[] = [
                'field' => $field,
                'old' => $values['old'] ?? null,
                'new' => $values['new'] ?? null,
            ];
        }

        return $formatted;
    }
}
