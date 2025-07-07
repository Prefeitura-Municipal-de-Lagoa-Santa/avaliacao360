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
        $roles = Role::with('permissions')->get();
        $permissions = Permission::all();
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


        $role->permissions()->sync($validated['permissions']);
        return back();
    }


}