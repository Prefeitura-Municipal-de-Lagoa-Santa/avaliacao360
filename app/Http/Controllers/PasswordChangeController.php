<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class PasswordChangeController extends Controller
{
    public function edit()
    {
        return Inertia::render('auth/ChangePassword');
    }

    public function update(Request $request)
    {
        $request->validate([
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $user = $request->user();
        $user->password = Hash::make($request->password);
        $user->must_change_password = false;
        $user->save();

        // Atribui a role 'Servidor' ao usuário (se ainda não tiver)
        $role = Role::firstOrCreate(['name' => 'Servidor'], ['level' => 1]);
        if (!$user->roles()->where('name', 'Servidor')->exists()) {
            $user->roles()->attach($role->id);
        }
        return redirect()->route('dashboard')->with('success', 'Senha atualizada com sucesso!');
    }
}
