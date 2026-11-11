<?php

use Illuminate\Support\Facades\Schedule;

/*
 * Run by cron on Hostinger (`* * * * * php artisan schedule:run`, Phase 5).
 */

// Tokens expired for more than a day (login tokens last 7 days).
Schedule::command('sanctum:prune-expired --hours=24')->daily();
