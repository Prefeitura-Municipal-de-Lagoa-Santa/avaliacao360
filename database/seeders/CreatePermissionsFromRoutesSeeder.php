<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Route;
use App\Models\Permission;

class CreatePermissionsFromRoutesSeeder extends Seeder
{
    public function run(): void
    {
        $routeNames = collect(Route::getRoutes())
            ->map(fn($route) => $route->getName())
            ->filter()
            ->unique();

        $created = 0;

        foreach ($routeNames as $name) {
            if (!Permission::where('name', $name)->exists()) {
                Permission::create(['name' => $name]);
                $created++;
            }
        }

        $this->command->info("Criadas $created permissões a partir dos nomes das rotas!");
    }
}
