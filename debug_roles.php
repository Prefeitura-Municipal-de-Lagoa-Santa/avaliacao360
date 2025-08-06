<?php

require_once 'vendor/autoload.php';
require_once 'bootstrap/app.php';

use App\Models\Role;
use App\Models\User;
use App\Models\Person;

echo "=== DEBUG ROLES ===\n\n";

// 1. Verificar se existem roles no sistema
echo "1. Roles no sistema:\n";
$roles = Role::all();
foreach ($roles as $role) {
    echo "   - {$role->name}\n";
}
echo "\n";

// 2. Verificar se existe a role "Comissão"
echo "2. Role 'Comissão' existe?\n";
$comissaoRole = Role::where('name', 'Comissão')->first();
if ($comissaoRole) {
    echo "   SIM - ID: {$comissaoRole->id}\n";
    
    // 3. Verificar usuários com essa role
    echo "\n3. Usuários com role 'Comissão':\n";
    $usersWithComissao = User::whereHas('roles', function ($query) {
        $query->where('name', 'Comissão');
    })->get();
    
    foreach ($usersWithComissao as $user) {
        echo "   - {$user->name} (CPF: {$user->cpf})\n";
        
        // Verificar se tem Person vinculada
        $person = Person::where('user_id', $user->id)->orWhere('cpf', $user->cpf)->first();
        if ($person) {
            echo "     → Pessoa vinculada: {$person->name} (ID: {$person->id})\n";
        } else {
            echo "     → PROBLEMA: Não tem Person vinculada\n";
        }
    }
} else {
    echo "   NÃO - A role 'Comissão' não existe\n";
}

// 4. Verificar pessoas com usuários
echo "\n4. Pessoas com usuários vinculados:\n";
$personsWithUsers = Person::whereNotNull('user_id')->with('user.roles')->get();
foreach ($personsWithUsers as $person) {
    echo "   - {$person->name} → Usuário: {$person->user->name}\n";
    $roleNames = $person->user->roles->pluck('name')->toArray();
    echo "     Roles: " . implode(', ', $roleNames) . "\n";
}

echo "\n=== FIM DEBUG ===\n";
