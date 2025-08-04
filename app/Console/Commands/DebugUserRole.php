<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class DebugUserRole extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'debug:user-role {cpf}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Debug user role and permissions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $cpf = $this->argument('cpf');
        
        $user = User::where('cpf', $cpf)->first();
        
        if (!$user) {
            $this->error("Usuário com CPF {$cpf} não encontrado.");
            return;
        }
        
        $this->info("=== DEBUG USUÁRIO ===");
        $this->line("Nome: {$user->name}");
        $this->line("CPF: {$user->cpf}");
        $this->line("Email: {$user->email}");
        
        $this->line("\n=== ROLES ===");
        if ($user->roles->count() > 0) {
            foreach ($user->roles as $role) {
                $this->line("- {$role->name}");
            }
        } else {
            $this->line("Nenhuma role encontrada.");
        }
        
        $this->line("\n=== VERIFICAÇÕES ===");
        $isRH = user_can('recourse');
        $isComissao = $user->roles->pluck('name')->contains('Comissão');
        
        $this->line("user_can('recourse'): " . ($isRH ? 'SIM' : 'NÃO'));
        $this->line("Tem role 'Comissão': " . ($isComissao ? 'SIM' : 'NÃO'));
        
        if ($isRH) {
            $this->line("=> RESULTADO: Usuário é RH");
        } elseif ($isComissao) {
            $this->line("=> RESULTADO: Usuário é Comissão");
        } else {
            $this->line("=> RESULTADO: Usuário não tem acesso a recursos");
        }
    }
}
