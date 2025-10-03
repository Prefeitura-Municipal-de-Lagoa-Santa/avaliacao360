<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use Illuminate\Support\Facades\DB;

class NormalizeRolesSeeder extends Seeder
{
    public function run(): void
    {
        // Unificar 'Diretoria RH' em 'Diretor RH'
        $legacy = Role::where('name', 'Diretoria RH')->first();
        $target = Role::where('name', 'Diretor RH')->first();

        if ($legacy && $target) {
            // Move role_user vínculos da legacy para target
            $userIds = DB::table('role_user')->where('role_id', $legacy->id)->pluck('user_id')->all();
            if (!empty($userIds)) {
                foreach ($userIds as $uid) {
                    DB::table('role_user')->updateOrInsert(
                        ['role_id' => $target->id, 'user_id' => $uid],
                        ['role_id' => $target->id, 'user_id' => $uid]
                    );
                }
            }
            // Remove vínculos antigos e a role legacy
            DB::table('role_user')->where('role_id', $legacy->id)->delete();
            DB::table('role_permission')->where('role_id', $legacy->id)->delete();
            $legacy->delete();
            $this->command?->info("Role 'Diretoria RH' mesclada em 'Diretor RH' e removida.");
        }

        // Outras normalizações de nomes semelhantes podem ser adicionadas aqui, se necessário.
    }
}
