<?php

namespace App\Console\Commands;

use App\Services\EpgImporter;
use Illuminate\Console\Command;
use Throwable;

class SyncEpgCommand extends Command
{
    protected $signature = 'sangatv:sync-epg {--auto-match : Isi ID EPG channel yang kosong berdasarkan nama}';

    protected $description = 'Ambil jadwal acara (XMLTV) dari sumber di Profil Hotel';

    public function handle(EpgImporter $importer): int
    {
        try {
            $r = $importer->sync();
            if ($this->option('auto-match') && ($n = EpgImporter::autoMatch()) > 0) {
                $this->info("{$n} channel dicocokkan otomatis, sinkron ulang…");
                $r = $importer->sync();
            }
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("{$r['channels']} channel terisi, {$r['programmes']} acara.");
        foreach ($r['sources'] as $s) {
            $this->line("  • {$s}");
        }

        return self::SUCCESS;
    }
}
