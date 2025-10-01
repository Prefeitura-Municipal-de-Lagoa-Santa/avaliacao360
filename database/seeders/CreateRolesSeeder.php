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
        $diretoriaRH = Role::updateOrCreate(['name' => 'Diretoria RH'], ['level' => 6]);
        $secretario  = Role::updateOrCreate(['name' => 'Secretário'], ['level' => 7]);
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
            'evaluations.completed.pdf',
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
            'recourse',
            'recourses.index',
            'recourses.review',
            'recourses.assignResponsible',
            'recourses.removeResponsible',
            'recourses.escalate',
            'recourses.return',
            'releases.generate',
            'reports',
            'storage.local',
            'users.manage-roles',
            'users.assign-role',
        ])->values());

            // Diretoria RH - homologação da 1ª instância
            $diretoriaRH->permissions()->sync($permissions->only([
                'recourses.review',
                'recourses.directorDecision',
                'storage.local',
            ])->values());

            // Secretário - decisão da 2ª instância
            $secretario->permissions()->sync($permissions->only([
                'recourses.review',
                'recourses.secretaryDecision',
                'storage.local',
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
            'notifications.destroy',
            'notifications.history',
            'notifications.mark-all-read',
            'notifications.mark-selected-read',
            'notifications.read',
            'notifications.unread',
            'notifications.delete-selected',
            'password.change',
            'password.update',
            'profile.cpf',
            'profile.cpf.update',
            'recourses.create',
            'recourses.show',
            'recourses.store',
            'recourses.acknowledge',
            'storage.local',
        ])->values());

        $this->command->info("Roles criadas e permissões atribuídas com sucesso.");
    }
}
