<?php

namespace App\Console\Commands;

use App\Models\Central\Hotel;
use App\Tenancy\HotelManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Throwable;

/** Run a per-hotel command in every active hotel (scheduler entry point). Single-hotel install: runs once. */
class ForEachHotelCommand extends Command
{
    protected $signature = 'sangatv:semua-hotel {perintah : mis. sangatv:check-channels} {--opsi=* : opsi tambahan, mis. --opsi=--media}';

    protected $description = 'Jalankan perintah di setiap hotel aktif';

    public function handle(HotelManager $tenancy): int
    {
        $command = (string) $this->argument('perintah');
        $options = collect($this->option('opsi'))->mapWithKeys(fn ($o) => [$o => true])->all();

        if (! $tenancy->enabled()) {
            return Artisan::call($command, $options, $this->output);
        }

        $failed = 0;
        foreach (Hotel::query()->where('status', 'active')->orderBy('id')->get() as $hotel) {
            $this->line("== {$hotel->slug}");
            try {
                $code = $tenancy->run($hotel, fn () => Artisan::call($command, $options, $this->output));
                $failed += $code === self::SUCCESS ? 0 : 1;
            } catch (Throwable $e) {
                $failed++;
                $this->error("{$hotel->slug}: ".$e->getMessage());
                report($e);
            }
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
