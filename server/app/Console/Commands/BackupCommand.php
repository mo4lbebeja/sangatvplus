<?php

namespace App\Console\Commands;

use App\Services\Notifier;
use App\Tenancy\HotelManager;
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
    protected $signature = 'sangatv:backup {--media : Sertakan arsip foto/media (otomatis tiap Minggu)} {--pusat : Backup database pusat (daftar hotel), bukan database hotel}';

    protected $description = 'Backup database (terenkripsi) ke server + Telegram';

    public function handle(Notifier $notifier): int
    {
        $password = (string) config('sangatv.backup.password');
        if ($password === '') {
            $this->error('BACKUP_PASSWORD belum diisi di .env');
            $notifier->send('⚠️ Backup tidak berjalan', 'BACKUP_PASSWORD belum diisi di .env server.');

            return self::FAILURE;
        }

        // Multi-hotel: one folder per hotel (and "pusat" for the central database); single-hotel: the folder itself.
        $hotel = app(HotelManager::class)->current();
        $this->scope = $this->option('pusat') ? 'pusat' : ($hotel?->slug ?? '');
        $dir = storage_path('app/private/backups'.($this->scope !== '' ? '/'.$this->scope : ''));
        File::ensureDirectoryExists($dir, 0700);
        $stamp = now()->format('Ymd-His');
        $made = [];

        try {
            $made[] = $this->encrypt($this->dumpDatabase("{$dir}/db-{$stamp}.sql.gz"), $password);
            if (! $this->option('pusat') && ($this->option('media') || now()->isSunday())) {
                $media = "{$dir}/media-{$stamp}.tar.gz";
                // This hotel's public files only (first hotel = the whole public folder minus other hotels).
                $root = config('filesystems.disks.public.root');
                $this->shell(['tar', '-czf', $media, '--exclude=./hotels', '-C', $root, '.']);
                $made[] = $this->encrypt($media, $password);
            }
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            $notifier->send('⚠️ Backup GAGAL', $e->getMessage());

            return self::FAILURE;
        }

        $offsite = [];
        $driveErrors = [];
        foreach ($made as $file) {
            $size = round(filesize($file) / 1048576, 1);
            $ok = $notifier->telegramFile($file, "Backup SangaTV Plus {$stamp} ({$size} MB, terenkripsi)");
            $line = basename($file)." {$size} MB".($ok ? ' → Telegram' : (filesize($file) > 49 * 1048576 ? ' (terlalu besar untuk Telegram)' : ' (Telegram belum diatur)'));
            if ($drive = $this->toCloud($file)) {
                $line .= $drive === true ? ' → Google Drive' : '';
                if ($drive !== true) {
                    $driveErrors[] = $drive;
                }
            }
            $offsite[] = $line;
        }
        if ($driveErrors !== []) {
            $notifier->send('⚠️ Backup ke Google Drive gagal', implode("\n", array_unique($driveErrors)).' (backup tetap tersimpan di server & Telegram)');
        }
        $this->pruneCloud();

        // Keep the newest N of each kind.
        foreach (['db-', 'media-'] as $prefix) {
            collect(glob("{$dir}/{$prefix}*.enc"))->sortDesc()->slice(config('sangatv.backup.keep'))->each(fn ($f) => @unlink($f));
        }

        $this->info(implode("\n", $offsite));

        return self::SUCCESS;
    }

    private function dumpDatabase(string $target): string
    {
        $c = config('database.connections.'.($this->option('pusat') ? config('tenancy.central_connection') : config('database.default')));
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

    /** @return true|string|null true = uploaded, string = error, null = not configured */
    private function toCloud(string $file): true|string|null
    {
        $remote = $this->remote();
        if (blank($remote)) {
            return null;
        }
        $res = Process::timeout(900)->run(['rclone', 'copy', $file, $remote, '--config', config('sangatv.backup.rclone_config')]);

        return $res->successful() ? true : 'rclone: '.trim(substr($res->errorOutput(), -300));
    }

    private string $scope = '';

    /** Cloud folder: <remote>/<hotel slug | pusat>. */
    private function remote(): ?string
    {
        $remote = config('sangatv.backup.rclone_remote');

        return blank($remote) ? null : rtrim($remote, '/').($this->scope !== '' ? '/'.$this->scope : '');
    }

    private function pruneCloud(): void
    {
        $remote = $this->remote();
        if (filled($remote)) {
            Process::timeout(300)->run(['rclone', 'delete', $remote, '--min-age', config('sangatv.backup.rclone_keep_days').'d', '--config', config('sangatv.backup.rclone_config')]);
        }
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
