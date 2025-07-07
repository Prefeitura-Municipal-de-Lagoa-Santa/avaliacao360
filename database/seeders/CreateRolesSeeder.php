<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;

class CreateRolesSeeder extends Seeder
{
    public function run(): void
    {
        // Cria as roles com o campo level
        $admin     = Role::updateOrCreate(['name' => 'Admin'], ['level' => 100]);
        $rh        = Role::updateOrCreate(['name' => 'RH'], ['level' => 50]);
        $comissao  = Role::updateOrCreate(['name' => 'Comissão'], ['level' => 30]);

        // Busca todas as permissões
        $allPermissions = Permission::all()->pluck('id')->toArray();

        // Admin recebe todas
        $admin->permissions()->sync($allPermissions);

        // (Opcional) RH e Comissão - ver comentários no seeder anterior

        $this->command->info("Roles criadas e permissões atribuídas!");
    }
}
