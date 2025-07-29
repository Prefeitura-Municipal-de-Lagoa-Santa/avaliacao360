<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class SystemNotification extends Notification
{
    public function __construct(
        public string $title,
        public string $content,
        public ?string $url = null
    ) {}

    public function via($notifiable)
    {
        return ['mail', 'database']; // envia por e-mail e salva no banco
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject($this->title)
            ->line($this->content)
            ->action('Acessar sistema', $this->url ?? url('/'));
    }

    public function toArray($notifiable)
    {
        return [
            'title' => $this->title,
            'content' => $this->content,
            'url' => $this->url,
        ];
    }
}
