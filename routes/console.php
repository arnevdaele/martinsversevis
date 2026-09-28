<?php

use Illuminate\Support\Facades\Schedule;

// Expired invitation / reset links for the portal.
Schedule::command('auth:clear-resets customers')->daily();

// Failed mails stay 30 days under Beheer › Mislukte e-mails, then are dropped.
Schedule::command('queue:prune-failed --hours=720')->daily();
