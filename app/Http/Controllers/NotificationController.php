<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class NotificationController extends Controller
{
    /**
     * Exibe todas as notificações do usuário (lidas e não lidas)
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $perPage = $request->get('per_page', 20);
        
        $notifications = $user->notifications()
            ->latest()
            ->paginate($perPage);

        return Inertia::render('Notifications/History', [
            'notifications' => $notifications
        ]);
    }

    /**
     * Retorna as notificações não lidas para o dropdown
     */
    public function unread()
    {
        $user = Auth::user();
        return $user ? $user->unreadNotifications()->latest()->take(10)->get() : [];
    }

    /**
     * Marca uma notificação como lida
     */
    public function markAsRead(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->where('id', $id)->firstOrFail();
        $notification->markAsRead();
        return response()->noContent();
    }

    /**
     * Marca todas as notificações como lidas
     */
    public function markAllAsRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();
        return back()->with('success', 'Todas as notificações foram marcadas como lidas');
    }

    /**
     * Exclui uma notificação
     */
    public function destroy(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->where('id', $id)->firstOrFail();
        $notification->delete();
        return response()->noContent();
    }
}
