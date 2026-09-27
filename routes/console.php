<?php

use Illuminate\Support\Facades\Schedule;

// Expired invitation / reset links for the portal.
Schedule::command('auth:clear-resets customers')->daily();

// Failed mail jobs are kept a week for inspection, then dropped.
Schedule::command('queue:prune-failed --hours=168')->daily();
