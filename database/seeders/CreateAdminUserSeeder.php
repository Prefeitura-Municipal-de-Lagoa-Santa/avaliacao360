<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

class CreateAdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Busca ou cria a role Admin
        $adminRole = Role::firstOrCreate(['name' => 'Admin'], ['level' => 100]);

        // Cria o usuário admin (altere email/senha se quiser)
        $user = User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'Administrador',
                'username' => 'admin',
                'cpf' => '12345678901', // Use um CPF válido ou deixe em
                'password' => Hash::make('admin123'), // Troque por uma senha forte em produção!
            ]
        );

        // Associa a role Admin ao usuário
        $user->roles()->syncWithoutDetaching([$adminRole->id]);

        // (Opcional) Adiciona todas as permissões diretamente ao admin (caso deseje)
        // $user->permissions()->syncWithoutDetaching(Permission::pluck('id')->toArray());

        $this->command->info('Usuário admin criado ou atualizado com sucesso!');
    }
}
