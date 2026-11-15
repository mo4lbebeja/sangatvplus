<?php

// Every per-hotel job runs through sangatv:semua-hotel (one run per active hotel; single-hotel install: once).

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Channel health every 10 minutes; prune old logs daily (needs the server cron: schedule:run every minute).
Schedule::command('sangatv:semua-hotel sangatv:check-channels')->everyTenMinutes()->withoutOverlapping();
Schedule::command('sangatv:semua-hotel model:prune')->daily();
Schedule::command('sangatv:semua-hotel sangatv:sync-epg')->everySixHours()->withoutOverlapping();
Schedule::command('sangatv:semua-hotel sangatv:uptime-report')->weeklyOn(1, '07:00')->timezone('Asia/Jakarta');
Schedule::command('sangatv:semua-hotel sangatv:monitor')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('sangatv:semua-hotel sangatv:backup')->dailyAt('02:30')->timezone('Asia/Jakarta')->withoutOverlapping();
Schedule::command('sangatv:semua-hotel sangatv:privasi-tamu')->dailyAt('03:15')->timezone('Asia/Jakarta');
Schedule::command('sangatv:backup --pusat')->dailyAt('02:20')->timezone('Asia/Jakarta')->withoutOverlapping()
    ->when(fn () => config('tenancy.enabled'));
Schedule::command('sangatv:pengingat-sewa')->dailyAt('08:00')->timezone('Asia/Jakarta')
    ->when(fn () => config('tenancy.enabled'));
