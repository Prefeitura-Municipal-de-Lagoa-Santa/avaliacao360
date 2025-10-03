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
    $diretorRh = Role::updateOrCreate(['name' => 'Diretor RH'], ['level' => 6]);
    $secretarioGestao = Role::updateOrCreate(['name' => 'Secretario Gestão'], ['level' => 6]);
    $secretario = Role::updateOrCreate(['name' => 'Secretário'], ['level' => 6]);
    $secretaria = Role::updateOrCreate(['name' => 'Secretaria'], ['level' => 6]);

        $permissions = Permission::all()->pluck('id', 'name');

        // Admin: tudo
        $admin->permissions()->sync($permissions->values());

        // RH (visualização e gestão de responsáveis; não inicia, não responde, não devolve)
        $rhPermissions = [
            'recourse',
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
            // Recursos (somente acompanhar e gerenciar responsáveis)
            'recourses.index',
            'recourses.review',
            'recourses.assignResponsible',
            'recourses.removeResponsible',
            'recourses.forwardToCommission',
            'releases.generate',
            'reports',
            'storage.local',
            'users.manage-roles',
            'users.assign-role',
        ];
        $rh->permissions()->sync($permissions->only($rhPermissions)->values());
    // Diretor RH herda as permissões do RH
    $diretorRh->permissions()->sync($permissions->only($rhPermissions)->values());

        // Acrescenta permissões específicas às roles de direção
        // Diretor RH: pode registrar decisão da DGP (e devolver à comissão, se existir)
        if ($permissions->has('recourses.dgpDecision')) {
            $diretorRh->permissions()->syncWithoutDetaching([$permissions['recourses.dgpDecision']]);
        }
        if ($permissions->has('recourses.dgpReturnToCommission')) {
            $diretorRh->permissions()->syncWithoutDetaching([$permissions['recourses.dgpReturnToCommission']]);
        }
        // Secretário (usuário comum com acesso a recursos): não herda RH; apenas permissões necessárias
        $secretaryPerms = [
            'recourse',
            'recourses.index',
            'recourses.review',
            'recourses.secretaryDecision',
            'storage.local',
        ];
        $secretarioGestao->permissions()->sync($permissions->only($secretaryPerms)->values());
        $secretario->permissions()->sync($permissions->only($secretaryPerms)->values());
        $secretaria->permissions()->sync($permissions->only($secretaryPerms)->values());

        // Comissão (atua no recurso)
        $comissao->permissions()->sync($permissions->only([
            'recourse',
            'recourses.index',
            'recourses.review',
            'recourses.personEvaluations',
            'recourses.markAnalyzing',
            'recourses.respond',
            'recourses.return',
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
            // Permissões do fluxo do servidor no recurso
            'recourses.acknowledgeFirst',
            'recourses.requestSecondInstance',
            'recourses.acknowledgeSecond',
            'storage.local',
        ])->values());

        $this->command->info("Roles criadas e permissões atribuídas com sucesso.");
    }
}
