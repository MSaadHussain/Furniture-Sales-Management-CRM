<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled tasks
|--------------------------------------------------------------------------
| Designed for shared hosting: a single cPanel cron entry calling
| `php artisan schedule:run` every minute drives everything below. No
| supervisor, no permanently running queue daemon (requirements 37).
*/

// Drains any queued work (database queue) and exits, so nothing has to stay
// resident between cron ticks.
Schedule::command('queue:work --stop-when-empty --max-time=55')
    ->everyMinute()
    ->withoutOverlapping();

// Housekeeping: trim expired framework caches and old session rows.
Schedule::command('auth:clear-resets')->daily();
