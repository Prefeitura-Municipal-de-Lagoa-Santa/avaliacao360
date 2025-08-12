<?php
namespace App\Providers;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;


class AppServiceProvider extends ServiceProvider
{
    public function boot(): void // Removido o tipo de retorno para compatibilidade com sua versão
    {
        if (app()->environment('production')) {
            // Força o uso de HTTPS em URLs geradas pelo Laravel
            URL::forceScheme('https');
        }
    }
}
