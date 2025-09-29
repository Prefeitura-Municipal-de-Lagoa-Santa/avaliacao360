<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCpfIsFilled
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Pega o usuário autenticado
        $user = Auth::user();

        // Se não está logado, só prossegue
        if (!$user) {
            return $next($request);
        }

        // Se o usuário tem alguma role com level >= 10, libera o acesso (NÃO bloqueia por CPF)
        if (
            $user->roles &&
            $user->roles->contains(fn($role) => $role->level >= 10)
        ) {
            return $next($request);
        }

        // Se o campo 'cpf' está vazio ou nulo
        if (!$user->cpf) {
            // Evita loop: libera para rota de editar CPF ou logout
            if (!$request->routeIs('profile.cpf', 'logout')) {
                return redirect()->route('profile.cpf');
            }
        }

        return $next($request);
    }
}
