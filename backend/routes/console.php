<?php

use App\Http\Controllers\Api\V1\System\HealthController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

/*
| Scheduler (ARCHITECTURE §6.15). Hostinger cron runs `schedule:run` every minute;
| there are no long-running workers on shared hosting, so the queue is drained here.
*/

Schedule::call(fn () => Cache::forever(HealthController::SCHEDULER_HEARTBEAT_KEY, now()->timestamp))
    ->name('scheduler-heartbeat')
    ->everyMinute();

Schedule::command('queue:work database --queue=critical,default,low --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping(5);

Schedule::command('queue:prune-failed --hours=720')->weekly();
Schedule::command('auth:clear-resets')->hourly();
