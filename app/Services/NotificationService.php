<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\SystemNotification;

class NotificationService
{
    public function notify(\Illuminate\Support\Collection|User|array $users, string $title, string $content, ?string $url = null): void
    {
        if ($users instanceof User) {
            $users = collect([$users]);
        } elseif (is_array($users)) {
            $users = collect($users);
        }

        foreach ($users as $user) {
            $user->notify(new SystemNotification($title, $content, $url));
        }
    }
}
