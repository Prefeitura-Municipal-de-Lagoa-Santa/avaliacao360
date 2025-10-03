<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserRoleController extends Controller
{
    /**
     * Página para gerenciamento de papéis
     */
    public function manageRoles()
    {
        $users = User::with('roles')->get(); // Carrega usuários com seus papéis
        $availableRoles = Role::where('name', '!=', 'Servidor')
            ->orderBy('level', 'desc')
            ->get(['name'])
            ->pluck('name');

        return Inertia::render('Admin/ManageRoles', [
            'users' => $users,
            'availableRoles' => $availableRoles,
        ]);
    }

    /**
     * Atribui ou remove papel extra (RH/Comissão), mantendo sempre o papel 'servidor'
     */
    public function assign(Request $request, User $user)
    {
        $validated = $request->validate([
            'role' => 'nullable|string|in:RH,Comissão,Diretor RH,Secretario Gestão',
        ]);

        $roleName = $validated['role'] ?? null;

        $servidorRole = Role::where('name', 'Servidor')->firstOrFail();

        if (empty($roleName)) {
            $user->roles()->sync([$servidorRole->id]);
        } else {
            if ($roleName === 'Servidor') {
                return back()->with('error', 'Seleção inválida de papel.');
            }
            $customRole = Role::where('name', $roleName)->firstOrFail();

            $user->roles()->sync([$servidorRole->id, $customRole->id]);
        }

        return back()->with('flash.success', 'Papéis atualizados com sucesso!');
    }

}
