<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Concerns\FormatsMailBadge;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class PasswordResetRequested extends Notification
{
    use FormatsMailBadge;

    public function __construct(
        public User $user,
        public Carbon $requestedAt,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Password Reset Requested for Your Admin Account')
            ->line($this->badgeHtml('SECURITY ALERT', '#d97706'))
            ->line("A password reset was requested for the \"{$this->user->name}\" administrator account, and a one-time verification code (OTP) was sent to this email address.")
            ->line('**Date & Time:** '.$this->requestedAt->format('m/d/Y g:i A'))
            ->line('If you did not request this, you can safely ignore this email — your password will not change unless the correct OTP is entered.')
            ->action('Go to Login', route('welcome'));
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Password Reset Requested',
            'description' => "A password reset OTP was requested for the \"{$this->user->name}\" account at {$this->requestedAt->format('m/d/Y g:i A')}.",
            'url' => route('admin.dashboard'),
            'icon' => 'shield-alert',
            'color' => 'warning',
        ];
    }
}
