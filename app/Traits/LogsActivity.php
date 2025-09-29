<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

trait LogsActivity
{
    /**
     * Boot do trait para registrar os observers
     */
    public static function bootLogsActivity()
    {
        // Log para criação
        static::created(function ($model) {
            static::logActivity($model, 'created', null, $model->toArray());
        });

        // Log para atualização
        static::updated(function ($model) {
            $changes = [];
            $oldValues = [];
            $newValues = [];

            foreach ($model->getDirty() as $key => $newValue) {
                $oldValue = $model->getOriginal($key);
                
                // Pula campos sensíveis por padrão
                if (in_array($key, ['password', 'remember_token', 'updated_at'])) {
                    continue;
                }

                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue
                ];
                $oldValues[$key] = $oldValue;
                $newValues[$key] = $newValue;
            }

            if (!empty($changes)) {
                static::logActivity($model, 'updated', $oldValues, $newValues, $changes);
            }
        });

        // Log para exclusão
        static::deleted(function ($model) {
            static::logActivity($model, 'deleted', $model->toArray(), null);
        });
    }

    /**
     * Registra a atividade no log
     */
    protected static function logActivity($model, $action, $oldValues = null, $newValues = null, $changes = null)
    {
        // Verificar se o modelo deve ser logado
        if (method_exists($model, 'shouldLogActivity') && !$model->shouldLogActivity($action)) {
            return;
        }

        $user = Auth::user();
        $request = Request::instance();

        $logData = [
            'model_type' => get_class($model),
            'model_id' => $model->getKey(),
            'action' => $action,
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'Sistema',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'changes' => $changes,
            'description' => static::getActivityDescription($model, $action, $changes),
        ];

        // Aplicar customizações se o método existir
        if (method_exists($model, 'customizeActivityLog')) {
            $logData = $model->customizeActivityLog($logData, $action);
        }

        ActivityLog::create($logData);
    }

    /**
     * Gera descrição da atividade
     */
    protected static function getActivityDescription($model, $action, $changes = null)
    {
        $modelName = class_basename(get_class($model));
        $user = Auth::user();
        $userName = $user?->name ?? 'Sistema';

        switch ($action) {
            case 'created':
                return "Novo {$modelName} criado";
            case 'updated':
                $fieldsChanged = $changes ? count($changes) : 0;
                return "{$modelName} atualizado ({$fieldsChanged} campos alterados)";
            case 'deleted':
                return "{$modelName} excluído";
            default:
                return "Ação {$action} executada em {$modelName}";
        }
    }

    /**
     * Relacionamento com os logs de atividade
     */
    public function activityLogs()
    {
        return $this->morphMany(ActivityLog::class, 'model', 'model_type', 'model_id');
    }

    /**
     * Obter logs de atividade mais recentes
     */
    public function getRecentActivityLogs($limit = 10)
    {
        return $this->activityLogs()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Log manual de atividade
     */
    public function logCustomActivity($action, $description = null, $data = [])
    {
        $user = Auth::user();
        $request = Request::instance();

        ActivityLog::create([
            'model_type' => get_class($this),
            'model_id' => $this->getKey(),
            'action' => $action,
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'Sistema',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'description' => $description,
            'new_values' => $data,
        ]);
    }
}
