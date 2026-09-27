<?php

namespace App\Notifications;

use App\Mail\Concerns\RespectsMailQuota;
use App\Models\CustomerUser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerInvitation extends Notification implements ShouldQueue
{
    use Queueable, RespectsMailQuota;

    public function __construct(public string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(CustomerUser $notifiable): MailMessage
    {
        $url = route('portal.password.reset', ['token' => $this->token, 'email' => $notifiable->email]);

        return (new MailMessage)
            ->subject(__('portal.mail.invitation.subject', ['app' => config('app.name')]))
            ->greeting(__('portal.mail.greeting', ['name' => $notifiable->name]))
            ->line(__('portal.mail.invitation.intro', ['customer' => $notifiable->customer->name]))
            ->action(__('portal.mail.invitation.action'), $url)
            ->line(__('portal.mail.invitation.expiry'));
    }
}
