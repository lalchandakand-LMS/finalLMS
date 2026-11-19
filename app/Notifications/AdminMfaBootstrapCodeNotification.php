<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminMfaBootstrapCodeNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly string $code) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Administrator sign-in security verification')
            ->greeting('Administrator security verification')
            ->line('A one-time code was requested to enroll an authenticator for your administrator account.')
            ->line('Your verification code is: '.$this->code)
            ->line('This code expires in 10 minutes and can be used once.')
            ->line('If you did not request this, do not share the code and contact the system owner.');
    }
}
