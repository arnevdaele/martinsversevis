<?php

namespace App\Jobs;

use App\Support\BackgroundHealth;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Proves a queue worker is alive: if this runs, it picks up mails too.
 * Unique, so a stopped worker finds one of these waiting, not a pile.
 */
class QueueHeartbeat implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 1;

    public int $uniqueFor = 3600;

    public function handle(): void
    {
        BackgroundHealth::beat(BackgroundHealth::QUEUE);
    }
}
