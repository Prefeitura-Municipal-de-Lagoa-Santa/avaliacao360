<?php

use App\Console\Commands\SendEvaluationNotifications;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Console\Commands\AutoGenerateEvaluations;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('send:evaluation-notifications', function () {
    $this->call(SendEvaluationNotifications::class);
})->purpose('Enviar notificações automáticas de avaliações e PDI');

Schedule::command(AutoGenerateEvaluations::class)->daily();