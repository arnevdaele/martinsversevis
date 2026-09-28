<?php

namespace App\Models;

use App\Mail\OrderConfirmation;
use App\Mail\OrderReceived;
use App\Notifications\CustomerInvitation;
use App\Notifications\CustomerResetPassword;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Str;
use Throwable;

/**
 * A queued job that gave up, as Laravel's failer writes it to `failed_jobs`.
 * In this app those are all mails (see RespectsMailQuota), so the helpers
 * below read who it was for out of the stored payload. Retrying goes through
 * `queue:retry`, which also resets the attempts and the 12-hour retry window.
 */
class FailedJob extends Model
{
    public $timestamps = false;

    /** Friendly names for what we queue; anything else shows its class name. */
    public const TYPES = [
        OrderConfirmation::class => 'Orderbevestiging (klant)',
        OrderReceived::class => 'Nieuwe bestelling (personeel)',
        CustomerInvitation::class => 'Uitnodiging portaal',
        CustomerResetPassword::class => 'Wachtwoord opnieuw instellen',
    ];

    private mixed $command = false;

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'failed_at' => 'datetime',
        ];
    }

    public function type(): string
    {
        $class = $this->payload['displayName'] ?? '';

        return self::TYPES[$class] ?? class_basename($class);
    }

    /** Who the mail was going to, or null when the payload can't be read (e.g. the order was deleted since). */
    public function recipient(): ?string
    {
        $command = $this->command();

        if ($command instanceof SendQueuedMailable) {
            return collect($command->mailable->to)->pluck('address')->filter()->implode(', ') ?: null;
        }

        if ($command instanceof SendQueuedNotifications) {
            return collect($command->notifiables)
                ->map(fn ($notifiable) => $notifiable->email ?? $notifiable->routes['mail'] ?? null)
                ->filter()->implode(', ') ?: null;
        }

        return null;
    }

    public function order(): ?Order
    {
        $command = $this->command();

        return $command instanceof SendQueuedMailable && ($command->mailable->order ?? null) instanceof Order
            ? $command->mailable->order
            : null;
    }

    /** The first line of the exception: usually enough to see it was the SMTP server. */
    public function reason(): string
    {
        // Stored as "Some\Exception\Class: message in /path/File.php:123"; keep the message.
        $line = Str::before(trim($this->exception), "\n");
        $line = preg_replace(['/^[\w\\\\]+: /', '/ in \S+:\d+$/'], '', $line);

        return Str::limit($line, 300);
    }

    private function command(): mixed
    {
        if ($this->command === false) {
            try {
                $this->command = unserialize($this->payload['data']['command'] ?? '');
            } catch (Throwable) {
                $this->command = null;
            }
        }

        return $this->command;
    }
}
