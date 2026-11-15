<?php

namespace App\Console\Commands;

use App\Services\Notifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;

/**
 * Daily encrypted database backup (+ weekly media archive): kept on the server and copied offsite
 * to Telegram. Restore: openssl enc -d -aes-256-cbc -pbkdf2 -in FILE.enc -out FILE.gz -pass env:BACKUP_PASSWORD
 */
class BackupCommand extends Command
{
    protected $signature = 'sangatv:backup {--media : Sertakan arsip foto/media (otomatis tiap Minggu)}';

    protected $description = 'Backup database (terenkripsi) ke server + Telegram';

    public function handle(Notifier $notifier): int
    {
        $password = (string) config('sangatv.backup.password');
        if ($password === '') {
            $this->error('BACKUP_PASSWORD belum diisi di .env');
            $notifier->send('⚠️ Backup tidak berjalan', 'BACKUP_PASSWORD belum diisi di .env server.');

            return self::FAILURE;
        }

        $dir = storage_path('app/private/backups');
        File::ensureDirectoryExists($dir, 0700);
        $stamp = now()->format('Ymd-His');
        $made = [];

        try {
            $made[] = $this->encrypt($this->dumpDatabase("{$dir}/db-{$stamp}.sql.gz"), $password);
            if ($this->option('media') || now()->isSunday()) {
                $media = "{$dir}/media-{$stamp}.tar.gz";
                $this->shell(['tar', '-czf', $media, '-C', storage_path('app'), 'public']);
                $made[] = $this->encrypt($media, $password);
            }
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            $notifier->send('⚠️ Backup GAGAL', $e->getMessage());

            return self::FAILURE;
        }

        $offsite = [];
        foreach ($made as $file) {
            $size = round(filesize($file) / 1048576, 1);
            $ok = $notifier->telegramFile($file, "Backup SangaTV Plus {$stamp} ({$size} MB, terenkripsi)");
            $offsite[] = basename($file)." {$size} MB".($ok ? ' → Telegram' : (filesize($file) > 49 * 1048576 ? ' (terlalu besar untuk Telegram)' : ' (Telegram belum diatur)'));
        }

        // Keep the newest N of each kind.
        foreach (['db-', 'media-'] as $prefix) {
            collect(glob("{$dir}/{$prefix}*.enc"))->sortDesc()->slice(config('sangatv.backup.keep'))->each(fn ($f) => @unlink($f));
        }

        $this->info(implode("\n", $offsite));

        return self::SUCCESS;
    }

    private function dumpDatabase(string $target): string
    {
        $c = config('database.connections.'.config('database.default'));
        $raw = substr($target, 0, -3);
        $driver = $c['driver'];
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $bin = trim(Process::run('command -v mariadb-dump || command -v mysqldump')->output()) ?: 'mysqldump';
            $res = Process::env(['MYSQL_PWD' => (string) $c['password']])->timeout(600)->run([
                $bin, '--single-transaction', '--quick', '--no-tablespaces', '-h', $c['host'], '-P', (string) $c['port'], '-u', $c['username'], $c['database'], '--result-file='.$raw,
            ]);
        } elseif ($driver === 'pgsql') {
            $res = Process::env(['PGPASSWORD' => (string) $c['password']])->timeout(600)->run([
                'pg_dump', '-h', $c['host'], '-p', (string) $c['port'], '-U', $c['username'], '-f', $raw, $c['database'],
            ]);
        } else {
            throw new RuntimeException("Driver database {$driver} belum didukung");
        }
        if (! $res->successful() || ! is_file($raw) || filesize($raw) < 100) {
            throw new RuntimeException('Dump database gagal: '.trim($res->errorOutput()));
        }
        $this->shell(['gzip', '-f', $raw]);

        return $target;
    }

    private function encrypt(string $file, string $password): string
    {
        $out = $file.'.enc';
        $res = Process::env(['BACKUP_PASSWORD' => $password])->timeout(600)
            ->run(['openssl', 'enc', '-aes-256-cbc', '-pbkdf2', '-salt', '-in', $file, '-out', $out, '-pass', 'env:BACKUP_PASSWORD']);
        if (! $res->successful()) {
            throw new RuntimeException('Enkripsi gagal: '.trim($res->errorOutput()));
        }
        @unlink($file);
        chmod($out, 0600);

        return $out;
    }

    private function shell(array $cmd): void
    {
        $res = Process::timeout(600)->run($cmd);
        if (! $res->successful()) {
            throw new RuntimeException(implode(' ', $cmd).': '.trim($res->errorOutput()));
        }
    }
}
