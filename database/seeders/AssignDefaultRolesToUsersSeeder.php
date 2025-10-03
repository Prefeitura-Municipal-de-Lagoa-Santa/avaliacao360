<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;

class AssignDefaultRolesToUsersSeeder extends Seeder
{
    public function run(): void
    {
        // Garante que a role Servidor exista
        $servidor = Role::firstOrCreate(['name' => 'Servidor'], ['level' => 1]);

        $countAttached = 0;

        User::with('roles')->lazyById()->each(function (User $user) use ($servidor, &$countAttached) {
            // Se o usuário não tiver nenhum papel, atribui 'Servidor'
            if ($user->roles->isEmpty()) {
                $user->roles()->syncWithoutDetaching([$servidor->id]);
                $countAttached++;
            }
        });

        $this->command->info("Papel 'Servidor' atribuído para {$countAttached} usuário(s) sem papéis.");
    }
}
