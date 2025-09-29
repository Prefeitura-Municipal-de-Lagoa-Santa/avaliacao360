<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    /**
     * Busca todos os roles com seus relacionamentos
     * e retorna para o Inertia.
     */
    public function index(): Response
    {
        // Carrega usuário, timeSlots e, dentro deles, a location
        $roles = Role::with('permissions')
            ->get();
        $permissions = Permission::all();
        // Breadcrumbs serão definidos no Vue
        return Inertia::render('roles/Index', [
            'roles' => $roles,
            'permissions' => $permissions
        ]);
    }

    public function updatePermissions(Request $request, Role $role): RedirectResponse
    {
        // Valida entrada
        $data = $request->validate([
            'role_id' => 'required|exists:roles,id',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);
        // Sincroniza relação many-to-many
        $role->permissions()->sync($data['permissions'] ?? []);

        // Redireciona de volta com flash message
        return redirect()
            ->back()
            ->with('flash.success', "Permissões do papel “{$role->name}” atualizadas com sucesso.");
    }

    public function create(): Response
    {
        return Inertia::render('roles/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        // 1) Validação dos campos
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'description' => 'nullable|string|max:500',
            'level' => 'required|integer|min:1',
        ]);
        // 2) Cria o role
        Role::create($data);

        // 3) Redireciona de volta para a listagem de perfis com flash de sucesso
        return redirect()
            ->route('roles.index')
            ->with('flash.success', 'Perfil «' . $data['name'] . '» criado com sucesso.');
    }

    public function edit(Role $role): Response
    {
        return Inertia::render('roles/Edit', ['role' => $role]);
    }

    /** Atualiza um perfil existente */
    public function update(Request $request, Role $role): RedirectResponse
    {

        $data = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,' . $role->id,
            'description' => 'nullable|string|max:500',
            'level' => 'required|integer|min:1',
        ]);

        $role->update($data);

        return redirect()
            ->route('roles.index')
            ->with('flash.success', "Perfil “{$role->name}” atualizado com sucesso.");
    }

    public function destroy(Role $role): RedirectResponse
    {
        $role->delete();

        return redirect()
            ->route('roles.index')
            ->with('flash.success', "Perfil “{$role->name}” deletado com sucesso.");
    }
}
