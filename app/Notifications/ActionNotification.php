<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class ActionNotification extends Notification
{
    public function __construct(
        public string $title,
        public string $description,
        public string $url,
        public string $icon = '',
        public string $color = 'info',
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'url' => $this->url,
            'icon' => $this->icon,
            'color' => $this->color,
        ];
    }
}
