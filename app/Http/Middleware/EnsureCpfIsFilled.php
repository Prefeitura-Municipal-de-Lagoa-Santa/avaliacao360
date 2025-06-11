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

        // Verifica se o usuário está logado e se o campo 'cpf' está vazio ou nulo.
        if ($user && !$user->cpf) {
            
            // Verifica se o usuário JÁ NÃO ESTÁ na página de editar perfil ou tentando deslogar.
            // Isso é crucial para evitar um loop de redirecionamento infinito.
            if (!$request->routeIs('profile.cpf', 'logout')) {
                
                // Redireciona para a página de edição de perfil com uma mensagem.
                // Troque 'profile.edit' pela rota correta da sua aplicação se for diferente.
                return redirect()->route('profile.cpf');
            }
        }

        // Se o CPF estiver preenchido ou a rota for permitida, deixa o usuário prosseguir.
        return $next($request);
    }
}
