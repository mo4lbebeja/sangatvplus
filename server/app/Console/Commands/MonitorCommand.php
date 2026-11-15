<?php

namespace App\Console\Commands;

use App\Enums\DeviceStatus;
use App\Models\Channel;
use App\Models\ChannelCheck;
use App\Models\Device;
use App\Services\Notifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Every 5 minutes: alert once when something breaks and once when it recovers
 * (channel down for N consecutive checks, TV silent for N hours).
 * Server-down itself must be watched from outside (e.g. UptimeRobot on /up).
 */
class MonitorCommand extends Command
{
    protected $signature = 'sangatv:monitor';

    protected $description = 'Pantau channel & TV, kirim peringatan (Telegram/email) saat bermasalah dan saat pulih';

    public function handle(Notifier $notifier): int
    {
        $n = (int) config('sangatv.alerts.channel_failed_checks');
        foreach (Channel::query()->where('is_active', true)->get() as $c) {
            $last = ChannelCheck::query()->where('channel_id', $c->id)->latest('checked_at')->limit($n)->pluck('status');
            $down = $last->count() >= $n && $last->every(fn ($s) => $s === 'error');
            $up = $last->first() === 'ok';
            $this->transition("channel:{$c->id}", $down, $up, $notifier,
                "Channel {$c->number} {$c->name} GAGAL", "Gagal pada {$n} pemeriksaan terakhir (±".($n * 10).' menit). Pesan: '.($c->health_message ?? '-'),
                "Channel {$c->number} {$c->name} normal kembali", 'Siaran bisa diputar lagi.');
        }

        $hours = (int) config('sangatv.alerts.tv_offline_hours');
        foreach (Device::query()->with('room')->where('status', DeviceStatus::Active)->whereNotNull('last_seen_at')->get() as $d) {
            // Only TVs that were alive in the last week (old/unused registrations don't page anyone).
            $silent = $d->last_seen_at->lt(now()->subHours($hours)) && $d->last_seen_at->gt(now()->subDays(7));
            $back = $d->last_seen_at->gt(now()->subMinutes(5));
            $room = $d->room?->number ?? '?';
            $this->transition("device:{$d->id}", $silent, $back, $notifier,
                "TV kamar {$room} tidak terhubung", 'Tidak ada sinyal sejak '.$d->last_seen_at->diffForHumans()." ({$d->model}). Cek listrik/Wi-Fi TV.",
                "TV kamar {$room} terhubung lagi", 'TV kembali mengirim sinyal ke server.');
        }

        return self::SUCCESS;
    }

    private function transition(string $key, bool $bad, bool $good, Notifier $notifier, string $badTitle, string $badBody, string $okTitle, string $okBody): void
    {
        $cacheKey = "alert:{$key}";
        if ($bad && ! Cache::has($cacheKey)) {
            Cache::forever($cacheKey, now()->toIso8601String());
            $notifier->send('⚠️ '.$badTitle, $badBody);
            $this->warn($badTitle);
        } elseif ($good && Cache::has($cacheKey)) {
            Cache::forget($cacheKey);
            $notifier->send('✅ '.$okTitle, $okBody);
            $this->info($okTitle);
        }
    }
}
