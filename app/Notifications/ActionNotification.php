<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

// The "something just happened" card for actions that have no notification
// of their own (bell + live card only, no email). Sent via ActionNotifier.
// Not ShouldQueue: this app has no reliable persistent queue worker.
class ActionNotification extends Notification
{
    public function __construct(
        public string $title,
        public string $description,
        public string $url,
        public string $icon = '',
        public string $color = 'info',
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{title: string, description: string, url: string, icon: string, color: string}
     */
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
