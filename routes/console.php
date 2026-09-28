<?php

use App\Jobs\QueueHeartbeat;
use App\Support\BackgroundHealth;
use Illuminate\Support\Facades\Schedule;

// Expired invitation / reset links for the portal.
Schedule::command('auth:clear-resets customers')->daily();

// Failed mails stay 30 days under Beheer › Mislukte e-mails, then are dropped.
Schedule::command('queue:prune-failed --hours=720')->daily();

// Heartbeats for the warning in the admin; see App\Support\BackgroundHealth.
Schedule::call(fn () => BackgroundHealth::beat(BackgroundHealth::SCHEDULER))->name('scheduler-heartbeat')->everyMinute();
Schedule::job(new QueueHeartbeat)->everyFiveMinutes();
