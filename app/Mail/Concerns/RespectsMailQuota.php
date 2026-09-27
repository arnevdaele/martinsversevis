<?php

namespace App\Mail\Concerns;

use DateTimeInterface;
use Illuminate\Queue\Middleware\RateLimited;

/**
 * Shared mail hosts cap how much one mailbox may send (OVH: about 200 per
 * hour) and block the account when that is exceeded. Every queued mail goes
 * through the "outgoing-mail" limiter instead: above the quota a job waits
 * its turn rather than failing, so inviting fifty customers at once simply
 * takes a little longer.
 *
 * Used by mailables (middleware()) and notifications (middleware($notifiable, $channel)).
 */
trait RespectsMailQuota
{
    /** Real errors (SMTP down, wrong password) still give up after a few tries. */
    public int $maxExceptions = 3;

    public function middleware(...$arguments): array
    {
        return [new RateLimited('outgoing-mail')];
    }

    /** Waiting for the quota is not a failure; keep trying for half a day. */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(12);
    }

    /** @return list<int> seconds between attempts after an exception */
    public function backoff(): array
    {
        return [60, 300, 900];
    }
}
