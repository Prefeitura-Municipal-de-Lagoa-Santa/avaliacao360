<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    /**
     */
    public function index()
    {
        if (!user_can('admin')) {
            $previous = url()->previous();
            return redirect(url()->previous())->with('error', 'Você não tem permissão para acessar essa área.');
        }

        $roles = Role::with('permissions')->get();
        $permissions = Permission::orderBy('name')->get();
        return inertia('Admin/Index', [
            'roles' => $roles,
            'permissions' => $permissions,
        ]);
    }

    public function updatePermissions(Request $request, Role $role)
    {
        $validated = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,id'], // ou 'integer' se for integer
        ]);

        // Capturar permissões atuais antes da mudança
        $oldPermissions = $role->permissions()->pluck('permissions.id')->toArray();
        
        // Aplicar as mudanças
        $role->permissions()->sync($validated['permissions']);
        
        // Capturar novas permissões
        $newPermissions = $validated['permissions'] ?? [];
        
        // Determinar quais permissões foram adicionadas e removidas
        $addedPermissions = array_diff($newPermissions, $oldPermissions);
        $removedPermissions = array_diff($oldPermissions, $newPermissions);
        
        // Registrar log personalizado se houve mudanças
        if (!empty($addedPermissions) || !empty($removedPermissions)) {
            $description = "Permissões do role '{$role->name}' foram atualizadas";
            
            $changes = [];
            
            if (!empty($addedPermissions)) {
                $addedNames = \App\Models\Permission::whereIn('id', $addedPermissions)->pluck('name')->toArray();
                $changes['added'] = $addedNames;
                $description .= " | Adicionadas: " . implode(', ', array_slice($addedNames, 0, 5));
                if (count($addedNames) > 5) {
                    $description .= " e mais " . (count($addedNames) - 5) . " permissões";
                }
            }
            
            if (!empty($removedPermissions)) {
                $removedNames = \App\Models\Permission::whereIn('id', $removedPermissions)->pluck('name')->toArray();
                $changes['removed'] = $removedNames;
                $description .= " | Removidas: " . implode(', ', array_slice($removedNames, 0, 5));
                if (count($removedNames) > 5) {
                    $description .= " e mais " . (count($removedNames) - 5) . " permissões";
                }
            }
            
            $role->logCustomActivity(
                'permissions_updated',
                $description,
                [
                    'old_permissions_count' => count($oldPermissions),
                    'new_permissions_count' => count($newPermissions),
                    'changes' => $changes
                ]
            );
        }

        return back();
    }


}