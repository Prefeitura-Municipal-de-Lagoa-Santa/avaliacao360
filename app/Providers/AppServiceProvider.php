<?php

use Illuminate\Support\ServiceProvider;
use App\Models\User;
use App\Models\Role;
use LdapRecord\Laravel\Events\Imported;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {

    }
}
