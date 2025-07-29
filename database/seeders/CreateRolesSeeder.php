<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;

class CreateRolesSeeder extends Seeder
{
    public function run(): void
    {
        $admin    = Role::updateOrCreate(['name' => 'Admin'], ['level' => 10]);
        $rh       = Role::updateOrCreate(['name' => 'RH'], ['level' => 5]);
        $comissao = Role::updateOrCreate(['name' => 'Comissão'], ['level' => 3]);
        $servidor = Role::updateOrCreate(['name' => 'Servidor'], ['level' => 1]);

        $permissions = Permission::all()->pluck('id', 'name');

        // Admin: tudo
        $admin->permissions()->sync($permissions->values());

        // RH
        $rh->permissions()->sync($permissions->only([
            'configs',
            'configs.create',
            'configs.destroy',
            'configs.edit',
            'configs.liberar.store',
            'configs.pdi.store',
            'configs.pdi.update',
            'configs.prazo.store',
            'configs.show',
            'configs.store',
            'configs.update',
            'dashboard',
            'evaluations.completed',
            'evaluations.pending',
            'funcoes.index',
            'funcoes.updateType',
            'organizational-char.index',
            'people.edit',
            'people.index',
            'people.manual.create',
            'people.manual.store',
            'people.update',
            'persons.confirm',
            'persons.preview',
            'recourses',
            'releases.generate',
            'reports',
            'storage.local',
            'users.manage-roles',
            'users.assign-role',
        ])->values());

        // Comissão
        $comissao->permissions()->sync($permissions->only([
            'recourse',
            'recourses.index',
            'recourses.markAnalyzing',
            'recourses.respond',
            'recourses.review',
            'storage.local',
        ])->values());

        // Servidor
        $servidor->permissions()->sync($permissions->only([
            'calendar',
            'evaluations',
            'evaluations.acknowledge',
            'evaluations.autoavaliacao.result',
            'evaluations.autoavaliacao.show',
            'evaluations.autoavaliacao.status',
            'evaluations.chefia.show',
            'evaluations.chefia.status',
            'evaluations.details',
            'evaluations.history',
            'evaluations.status',
            'evaluations.store',
            'evaluations.subordinate.show',
            'evaluations.subordinates.list',
            'login',
            'logout',
            'password.change',
            'password.update',
            'profile.cpf',
            'profile.cpf.update',
            'recourses.create',
            'recourses.show',
            'recourses.store',
            'storage.local',
        ])->values());

        $this->command->info("Roles criadas e permissões atribuídas com sucesso.");
    }
}
