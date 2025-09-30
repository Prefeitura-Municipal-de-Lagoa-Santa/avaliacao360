<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class RedirectIfMustChangePassword
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // Se não estiver logado, ignora
        if (!$user) {
            return $next($request);
        }

        // Se precisa trocar a senha e não está na rota de troca
        if ($user->must_change_password && !Route::is('password.change', 'password.update')) {
            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
