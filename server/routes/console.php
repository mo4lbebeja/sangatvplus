<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Channel health every 10 minutes; prune old logs daily (needs the server cron: schedule:run every minute).
Schedule::command('sangatv:check-channels')->everyTenMinutes()->withoutOverlapping();
Schedule::command('model:prune')->daily();
