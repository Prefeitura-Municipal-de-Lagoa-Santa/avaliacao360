<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Outras seeds se quiser...
        $this->call([
            \Database\Seeders\CreatePermissionsFromRoutesSeeder::class,
            \Database\Seeders\CreateRolesSeeder::class,
            \Database\Seeders\NormalizeRolesSeeder::class,
            \Database\Seeders\CreateAdminUserSeeder::class,
            \Database\Seeders\AssignDefaultRolesToUsersSeeder::class,
            \Database\Seeders\JobFunctionSeeder::class,
        ]);

    }
}
