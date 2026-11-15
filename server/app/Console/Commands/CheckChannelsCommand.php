<?php

namespace App\Console\Commands;

use App\Services\ChannelHealthChecker;
use Illuminate\Console\Command;

class CheckChannelsCommand extends Command
{
    protected $signature = 'sangatv:check-channels';

    protected $description = 'Cek kesehatan semua channel aktif (manifest HLS/DASH)';

    public function handle(ChannelHealthChecker $checker): int
    {
        $n = $checker->checkAll();
        $this->info("Normal: {$n['ok']} · Gagal: {$n['error']} · Tidak dicek (multicast): {$n['skipped']}");

        return self::SUCCESS;
    }
}
