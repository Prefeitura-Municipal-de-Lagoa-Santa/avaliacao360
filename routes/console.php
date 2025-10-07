<?php

use App\Console\Commands\SendEvaluationNotifications;
use App\Console\Commands\AutoGenerateEvaluations;
use App\Console\Commands\RecalculateQuestionWeights;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Comando motivacional
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Comando manual de envio de notificações
Artisan::command('send:evaluation-notifications', function () {
    $this->call(SendEvaluationNotifications::class);
})->purpose('Enviar notificações automáticas de avaliações e PDI');

// Agendamento automático diário das notificações (às 08:00)
Schedule::command(SendEvaluationNotifications::class)->dailyAt('08:00');

// Agendamento automático diário de geração de avaliações (às 06:00)
Schedule::command(AutoGenerateEvaluations::class)->dailyAt('06:00');

// Comando para recalcular pesos das questões
Artisan::command('forms:recalculate-weights {--form-id= : ID específico do formulário}', function () {
    $this->call(RecalculateQuestionWeights::class, [
        '--form-id' => $this->option('form-id')
    ]);
})->purpose('Recalcula os pesos das questões dos formulários');
