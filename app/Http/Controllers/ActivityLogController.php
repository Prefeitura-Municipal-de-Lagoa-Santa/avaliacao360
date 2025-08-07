<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ActivityLogController extends Controller
{
    /**
     * Exibir lista de logs de atividade
     */
    public function index(Request $request)
    {
        $query = ActivityLog::query()
            ->with(['user', 'model'])
            ->orderBy('created_at', 'desc');

        // Filtros
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('user_name', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%")
                  ->orWhere('action', 'like', "%{$search}%");
            });
        }

        if ($request->filled('action')) {
            $query->where('action', $request->get('action'));
        }

        if ($request->filled('model_type')) {
            $query->where('model_type', $request->get('model_type'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->get('user_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->get('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->get('date_to'));
        }

        $logs = $query->paginate(50);

        // Opções para filtros
        $actions = ActivityLog::distinct()->pluck('action')->filter()->sort()->values();
        $modelTypes = ActivityLog::distinct()->pluck('model_type')->filter()->map(function ($type) {
            return [
                'value' => $type,
                'label' => class_basename($type)
            ];
        })->sort()->values();

        return Inertia::render('ActivityLogs/Index', [
            'logs' => $logs,
            'filters' => $request->only(['search', 'action', 'model_type', 'user_id', 'date_from', 'date_to']),
            'filterOptions' => [
                'actions' => $actions,
                'modelTypes' => $modelTypes,
            ]
        ]);
    }

    /**
     * Exibir detalhes de um log específico
     */
    public function show(ActivityLog $activityLog)
    {
        $activityLog->load(['user', 'model']);

        return Inertia::render('ActivityLogs/Show', [
            'log' => $activityLog
        ]);
    }

    /**
     * Limpar logs antigos
     */
    public function cleanup(Request $request)
    {
        $request->validate([
            'days' => 'required|integer|min:1|max:365'
        ]);

        $deleted = ActivityLog::where('created_at', '<', now()->subDays($request->days))->delete();

        return back()->with('message', "Foram removidos {$deleted} logs de atividade.");
    }
}
