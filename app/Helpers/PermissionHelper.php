<?php

use Illuminate\Support\Facades\Auth;

/**
 * Verifica se o usuário autenticado tem a permissão,
 * seja direta ou via qualquer role.
 *
 * @param string $permission
 * @return bool
 */
function user_can(string $permission): bool
{
    $user = Auth::user();
    if (!$user) return false;

    // Permissão direta
    if (method_exists($user, 'permissions') && $user->permissions->pluck('name')->contains($permission)) {
        return true;
    }

    // Permissão via roles
    if (method_exists($user, 'roles')) {
        foreach ($user->roles as $role) {
            if (method_exists($role, 'permissions') && $role->permissions->pluck('name')->contains($permission)) {
                return true;
            }
        }
    }

    return false;
}
