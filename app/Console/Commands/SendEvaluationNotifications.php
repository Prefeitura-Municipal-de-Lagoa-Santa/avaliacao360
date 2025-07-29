<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Form;
use App\Models\User;
use App\Notifications\SystemNotification;

class SendEvaluationNotifications extends Command
{
    protected $signature = 'send:evaluation-notifications';
    protected $description = 'Envia notificações automáticas sobre avaliações e PDI';

    public function handle(): int
    {
        $year = (in_array(date('n'), [1, 2])) ? date('Y') - 1 : date('Y');
        $hoje = now();

        $form = Form::where('year', $year)
            ->where('type', 'servidor')
            ->where('release', true)
            ->first();

        if ($form && $form->term_first && $form->term_first->isToday()) {
            $title = 'Início do prazo para responder avaliações';
            $content = 'Hoje começa o período para preencher as avaliações. Acesse agora.';
            $url = route('evaluations');

            $users = User::all();

            foreach ($users as $user) {
                $jaRecebeu = $user->notifications()
                    ->whereDate('created_at', today())
                    ->where('type', SystemNotification::class)
                    ->where('data->title', $title)
                    ->exists();

                if (! $jaRecebeu) {
                    $user->notify(new SystemNotification($title, $content, $url));
                }
            }

            $this->info('Notificações de início de avaliação enviadas.');
        }

        return self::SUCCESS;
    }
}
