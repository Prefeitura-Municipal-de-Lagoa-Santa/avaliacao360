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
        $admin     = Role::updateOrCreate(['name' => 'Admin'], ['level' => 10]);
        $rh        = Role::updateOrCreate(['name' => 'RH'], ['level' => 5]);
        $comissao  = Role::updateOrCreate(['name' => 'Comissão'], ['level' => 3]);
        $servidor  = Role::updateOrCreate(['name' => 'Servidor'], ['level' => 1]);

        // Busca todas as permissões
        $allPermissions = Permission::all()->pluck('id')->toArray();

        // Admin recebe todas
        $admin->permissions()->sync($allPermissions);

        // Servidor recebe apenas algumas permissões específicas
        $servidorPermissions = Permission::whereIn('name', [
            'evaluations',
            'pdi',
            'calendar',
        ])->pluck('id')->toArray();

        $servidor->permissions()->sync($servidorPermissions);

        $this->command->info("Roles criadas e permissões atribuídas!");
    }
}
