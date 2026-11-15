<?php

namespace App\Vendor\Pages;

use App\Models\Central\FleetSnapshot;
use App\Models\Central\Hotel;
use App\Vendor\Concerns\VendorAccess;
use App\Vendor\HotelStats;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use UnitEnum;

/** Health of the VPS and the platform: disk, memory, load, database sizes, backups, scheduler. */
class ServerMonitor extends Page
{
    use VendorAccess;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static string|UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Monitor Server';

    protected static ?string $title = 'Monitor Server';

    protected static ?string $slug = 'monitor-server';

    protected string $view = 'vendor-console.pages.server-monitor';

    protected ?string $pollingInterval = null;

    protected static function area(): string
    {
        return 'owner';
    }

    protected function getViewData(): array
    {
        $path = base_path();
        $total = @disk_total_space($path) ?: 0;
        $free = @disk_free_space($path) ?: 0;
        $mem = $this->memory();
        $load = function_exists('sys_getloadavg') ? (sys_getloadavg() ?: [0, 0, 0]) : [0, 0, 0];
        $cpus = (int) (@shell_exec('nproc 2>/dev/null') ?: @shell_exec('sysctl -n hw.ncpu 2>/dev/null') ?: 1);
        $hotels = Hotel::query()->orderBy('name')->get();
        $sizes = $this->databaseSizes($hotels->pluck('database')->push(config('database.connections.central.database'))->unique()->all());
        $lastSnapshot = FleetSnapshot::query()->max('taken_at');
        $logs = collect(File::glob(storage_path('logs/*.log')) ?: [])->sum(fn ($f) => @filesize($f));

        return [
            'disk' => ['total' => $total, 'used' => $total - $free, 'pct' => $total ? round(($total - $free) / $total * 100) : 0],
            'mem' => $mem,
            'load' => $load, 'cpus' => max(1, $cpus),
            'php' => PHP_VERSION, 'laravel' => app()->version(), 'env' => app()->environment(),
            'uptime' => trim((string) @shell_exec('uptime -p 2>/dev/null')) ?: null,
            'hotels' => $hotels->map(fn (Hotel $h) => ['hotel' => $h, 'db' => $sizes[$h->database] ?? null, 'backup' => HotelStats::lastBackup($h),
                'media' => $this->dirSize(storage_path('app/public'.($h->storage_dir ? '/'.$h->storage_dir : '')), $h->storage_dir === '')]),
            'central' => $sizes[config('database.connections.central.database')] ?? null,
            'central_backup' => $this->newest(storage_path('app/private/backups/pusat')),
            'snapshot' => $lastSnapshot ? Carbon::parse($lastSnapshot) : null,
            'logs' => $logs,
            'drive' => filled(config('sangatv.backup.rclone_remote')),
            'telegram' => filled(config('sangatv.telegram.bot_token')),
            'mail' => ! in_array(config('mail.default'), ['log', 'array'], true),
        ];
    }

    private function memory(): ?array
    {
        $raw = @file_get_contents('/proc/meminfo');
        if (! $raw) {
            return null;
        }
        preg_match('/MemTotal:\s+(\d+)/', $raw, $t);
        preg_match('/MemAvailable:\s+(\d+)/', $raw, $a);
        $total = (int) ($t[1] ?? 0) * 1024;
        $avail = (int) ($a[1] ?? 0) * 1024;

        return $total ? ['total' => $total, 'used' => $total - $avail, 'pct' => round(($total - $avail) / $total * 100)] : null;
    }

    /** @return array<string, int> */
    private function databaseSizes(array $names): array
    {
        $c = DB::connection('central');

        return rescue(function () use ($c, $names) {
            if ($c->getDriverName() === 'pgsql') {
                return collect($names)->mapWithKeys(fn ($n) => [$n => (int) ($c->selectOne('SELECT pg_database_size(?) AS s', [$n])->s ?? 0)])->all();
            }
            $rows = $c->select('SELECT table_schema AS n, SUM(data_length + index_length) AS s FROM information_schema.tables WHERE table_schema IN ('.implode(',', array_fill(0, count($names), '?')).') GROUP BY table_schema', $names);

            return collect($rows)->mapWithKeys(fn ($r) => [$r->n => (int) $r->s])->all();
        }, [], false);
    }

    private function dirSize(string $dir, bool $excludeHotels): int
    {
        if (! is_dir($dir)) {
            return 0;
        }
        $size = 0;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($excludeHotels && str_contains($f->getPathname(), '/hotels/')) {
                continue;
            }
            $size += $f->getSize();
        }

        return $size;
    }

    private function newest(string $dir): ?int
    {
        $t = array_map('filemtime', File::glob($dir.'/db-*.enc') ?: []);

        return $t ? max($t) : null;
    }

    public static function bytes(?int $b): string
    {
        if ($b === null) {
            return '–';
        }
        foreach (['B', 'KB', 'MB', 'GB', 'TB'] as $u) {
            if ($b < 1024) {
                return number_format($b, $u === 'B' ? 0 : 1, ',', '.').' '.$u;
            }
            $b /= 1024;
        }

        return number_format($b, 1, ',', '.').' PB';
    }
}
