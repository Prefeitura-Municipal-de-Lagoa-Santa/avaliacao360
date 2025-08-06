<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Role;
use App\Models\User;
use App\Models\Person;

class DebugRoles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'debug:roles';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Debug roles and user assignments';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== DEBUG ROLES ===');
        $this->line('');

        // 1. Verificar se existem roles no sistema
        $this->info('1. Roles no sistema:');
        $roles = Role::all();
        foreach ($roles as $role) {
            $this->line("   - {$role->name}");
        }
        $this->line('');

        // 2. Verificar se existe a role "Comissão"
        $this->info('2. Role "Comissão" existe?');
        $comissaoRole = Role::where('name', 'Comissão')->first();
        if ($comissaoRole) {
            $this->line("   SIM - ID: {$comissaoRole->id}");
            
            // 3. Verificar usuários com essa role
            $this->line('');
            $this->info('3. Usuários com role "Comissão":');
            $usersWithComissao = User::whereHas('roles', function ($query) {
                $query->where('name', 'Comissão');
            })->get();
            
            foreach ($usersWithComissao as $user) {
                $this->line("   - {$user->name} (CPF: {$user->cpf})");
                
                // Verificar se tem Person vinculada
                $person = Person::where('user_id', $user->id)->orWhere('cpf', $user->cpf)->first();
                if ($person) {
                    $this->line("     → Pessoa vinculada: {$person->name} (ID: {$person->id})");
                } else {
                    $this->line("     → PROBLEMA: Não tem Person vinculada");
                }
            }
        } else {
            $this->line('   NÃO - A role "Comissão" não existe');
        }

        // 4. Verificar pessoas com usuários
        $this->line('');
        $this->info('4. Pessoas com usuários vinculados (primeiras 10):');
        $personsWithUsers = Person::whereNotNull('user_id')->with('user.roles')->take(10)->get();
        foreach ($personsWithUsers as $person) {
            $this->line("   - {$person->name} → Usuário: {$person->user->name}");
            $roleNames = $person->user->roles->pluck('name')->toArray();
            $this->line("     Roles: " . (empty($roleNames) ? 'Nenhuma' : implode(', ', $roleNames)));
        }

        // 4.1. Verificar pessoas vinculadas por CPF
        $this->line('');
        $this->info('4.1. Pessoas vinculadas por CPF aos usuários com role Comissão:');
        $usersWithComissao = User::whereHas('roles', function ($query) {
            $query->where('name', 'Comissão');
        })->get();
        
        foreach ($usersWithComissao as $user) {
            $this->line("   Usuário: {$user->name} (CPF: {$user->cpf})");
            
            // Verificar person por user_id
            $personByUserId = Person::where('user_id', $user->id)->first();
            if ($personByUserId) {
                $this->line("     → Person por user_id: {$personByUserId->name} (ID: {$personByUserId->id})");
            }
            
            // Verificar person por CPF
            $personByCpf = Person::where('cpf', $user->cpf)->first();
            if ($personByCpf) {
                $this->line("     → Person por CPF: {$personByCpf->name} (ID: {$personByCpf->id})");
                $this->line("       user_id da person: " . ($personByCpf->user_id ?? 'NULL'));
            }
        }

        // 5. Teste da query específica
        $this->line('');
        $this->info('5. Testando query específica do controller (original):');
        $availablePersonsOld = Person::whereHas('user.roles', function ($query) {
                $query->where('name', 'Comissão');
            })
            ->select('id', 'name', 'registration_number')
            ->orderBy('name')
            ->get();
            
        $this->line("   Resultado query original: {$availablePersonsOld->count()} pessoas encontradas");

        // 5.1. Teste da nova query
        $this->info('5.1. Testando nova query corrigida:');
        $availablePersonsNew = Person::whereIn('cpf', function ($query) {
                $query->select('cpf')
                    ->from('users')
                    ->whereExists(function ($subQuery) {
                        $subQuery->select('*')
                            ->from('role_user')
                            ->join('roles', 'roles.id', '=', 'role_user.role_id')
                            ->whereColumn('role_user.user_id', 'users.id')
                            ->where('roles.name', 'Comissão');
                    });
            })
            ->select('id', 'name', 'registration_number')
            ->orderBy('name')
            ->get();
            
        $this->line("   Resultado query nova: {$availablePersonsNew->count()} pessoas encontradas");
        foreach ($availablePersonsNew as $person) {
            $this->line("   - {$person->name} (#{$person->registration_number})");
        }

        $this->line('');
        $this->info('=== FIM DEBUG ===');
    }
}
