<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Form;
use App\Models\User;
use App\Models\EvaluationRecourse as Recourse;
use App\Notifications\SystemNotification;

class SendEvaluationNotifications extends Command
{
    protected $signature = 'send:evaluation-notifications';
    protected $description = 'Envia notificações automáticas sobre avaliações, PDI e recursos';

    public function handle(): int
    {
        $hoje = now();
        $year = ($hoje->month <= 2) ? $hoje->year - 1 : $hoje->year;
        $users = User::all();

        // 1. Liberação de avaliações
        $liberadasHoje = Form::where('year', $year)
            ->whereIn('type', ['servidor', 'gestor', 'chefia', 'comissionado'])
            ->where('release', true)
            ->whereDate('created_at', $hoje)
            ->exists();

        if ($liberadasHoje) {
            $this->notifyAll($users, 'Avaliações liberadas', 'Os formulários de avaliação foram liberados para preenchimento.', route('evaluations'));
        }

        // 2. Início do prazo para responder avaliações
        $formInicio = Form::where('year', $year)
            ->whereIn('type', ['servidor', 'gestor', 'chefia', 'comissionado'])
            ->whereDate('term_first', $hoje)
            ->first();

        if ($formInicio) {
            $this->notifyAll($users, 'Início do prazo para responder avaliações', 'Hoje começa o período de avaliações. Acesse a plataforma.', route('evaluations'));
        }

        // 3. Fim próximo do prazo (3 dias antes)
        $formFim = Form::where('year', $year)
            ->whereIn('type', ['servidor', 'gestor', 'chefia', 'comissionado'])
            ->whereDate('term_end', $hoje->copy()->addDays(3))
            ->first();

        if ($formFim) {
            $this->notifyAll($users, 'Prazo final para responder avaliações', 'Faltam apenas 3 dias para encerrar o prazo de avaliações.', route('evaluations'));
        }

        // 4. Liberação da nota (opcional, comentado)
        // if ($hoje->toDateString() === '2025-01-20') {
        //     $this->notifyAll($users, 'Notas liberadas', 'As notas das avaliações foram liberadas.', route('evaluations'));
        // }

        // 5. Liberação de PDI
        $pdiLiberado = Form::where('year', $year)
            ->whereIn('type', ['pactuacao_servidor', 'pactuacao_gestor', 'pactuacao_comissionado'])
            ->where('release', true)
            ->whereDate('created_at', $hoje)
            ->first();

        if ($pdiLiberado) {
            $this->notifyAll($users, 'PDI liberado', 'O Plano de Desenvolvimento Individual (PDI) foi liberado.', route('pdi'));
        }

        // 6. Início do prazo de PDI
        $pdiInicio = Form::where('year', $year)
            ->whereIn('type', ['pactuacao_servidor', 'pactuacao_gestor', 'pactuacao_comissionado'])
            ->whereDate('term_first', $hoje)
            ->first();

        if ($pdiInicio) {
            $this->notifyAll($users, 'Início do prazo para preencher o PDI', 'Hoje inicia o prazo de preenchimento do PDI.', route('pdi'));
        }

        // 7. Fim próximo do prazo do PDI
        $pdiFim = Form::where('year', $year)
            ->whereIn('type', ['pactuacao_servidor', 'pactuacao_gestor', 'pactuacao_comissionado'])
            ->whereDate('term_end', $hoje->copy()->addDays(3))
            ->first();

        if ($pdiFim) {
            $this->notifyAll($users, 'Prazo do PDI terminando', 'Faltam 3 dias para o fim do preenchimento do PDI.', route('pdi'));
        }

        // 8. Recurso em análise
        $recursosNovos = Recourse::whereDate('created_at', $hoje)->get();

        foreach ($recursosNovos as $rec) {
            if ($rec->person?->user) {
                $this->notifyIfNotSent($rec->person->user, 'Recurso em análise', 'Seu recurso foi registrado e está em análise.', route('recourse'));
            }
        }

        // 9 e 10. Recurso respondido / indeferido
        $recursosRespondidos = Recourse::whereDate('responded_at', $hoje)->get();

        foreach ($recursosRespondidos as $rec) {
            if ($rec->person?->user) {
                $titulo = $rec->status === 'indeferido'
                    ? 'Recurso indeferido'
                    : 'Recurso respondido';

                $this->notifyIfNotSent($rec->person->user, $titulo, 'Seu recurso foi analisado. Acesse para visualizar.', route('recourse'));
            }
        }

        $this->info('Todas as notificações automáticas foram processadas com sucesso.');
        return self::SUCCESS;
    }

    private function notifyAll($users, string $title, string $content, ?string $url)
    {
        foreach ($users as $user) {
            $this->notifyIfNotSent($user, $title, $content, $url);
        }
    }

    private function notifyIfNotSent(User $user, string $title, string $content, ?string $url)
    {
        $alreadySent = $user->notifications()
            ->whereDate('created_at', today())
            ->where('type', SystemNotification::class)
            ->where('data->title', $title)
            ->exists();

        if (! $alreadySent) {
            $user->notify(new SystemNotification($title, $content, $url));
        }
    }
}
