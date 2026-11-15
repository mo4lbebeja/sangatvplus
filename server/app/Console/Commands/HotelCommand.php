<?php

namespace App\Console\Commands;

use App\Models\Central\Hotel;
use App\Tenancy\HotelManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Throwable;

/** Sijalucom: manage hotels renting SangaTV Plus (central database). */
class HotelCommand extends Command
{
    protected $signature = 'sangatv:hotel {aksi : daftar | buat | daftarkan | migrasi | status}
        {kode? : Kode Hotel, mis. napolin}
        {--nama= : nama hotel} {--email= : email admin hotel (buat)} {--database= : database yang sudah ada (daftarkan)}
        {--storage= : folder berkas publik (daftarkan); kosong = folder utama} {--status= : active | suspended (status)}';

    protected $description = 'Kelola hotel penyewa: daftar, buat, daftarkan database lama, migrasi, ubah status';

    public function handle(HotelManager $tenancy): int
    {
        return match ($this->argument('aksi')) {
            'daftar' => $this->list(),
            'buat' => $this->create($tenancy),
            'daftarkan' => $this->register(),
            'migrasi' => $this->migrateAll($tenancy),
            'status' => $this->setStatus(),
            default => $this->failWith('Aksi tidak dikenal.'),
        };
    }

    private function list(): int
    {
        $this->table(['Kode', 'Nama', 'Database', 'Status', 'Layanan sampai'], Hotel::query()->orderBy('id')->get()->map(fn (Hotel $h) => [
            $h->slug, $h->name, $h->database, $h->status, $h->service_expires_at?->toDateString() ?? 'tanpa batas',
        ]));

        return self::SUCCESS;
    }

    private function create(HotelManager $tenancy): int
    {
        $slug = (string) $this->argument('kode');
        $name = (string) $this->option('nama');
        $email = (string) $this->option('email');
        if ($slug === '' || $name === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->failWith('Pakai: sangatv:hotel buat <kode> --nama="Nama Hotel" --email=admin@hotel.com');
        }
        try {
            $r = $tenancy->provision($slug, $name, $email);
        } catch (Throwable $e) {
            return $this->failWith($e->getMessage());
        }
        $this->info("Hotel {$r['hotel']->name} dibuat (kode: {$slug}, database: {$r['hotel']->database}).");
        $this->line("Login CMS: Kode Hotel {$slug} · {$email} · kata sandi sementara: {$r['password']}");
        $this->warn('Simpan kata sandi ini sekarang; admin wajib mengaktifkan verifikasi dua langkah saat login pertama.');

        return self::SUCCESS;
    }

    /** Register an existing hotel database (e.g. the first hotel that ran single-hotel before). */
    private function register(): int
    {
        $slug = (string) $this->argument('kode');
        $db = (string) $this->option('database');
        if (! HotelManager::validSlug($slug) || $db === '' || ! $this->option('nama')) {
            return $this->failWith('Pakai: sangatv:hotel daftarkan <kode> --nama="Nama" --database=<nama_db> [--storage=""]');
        }
        $hotel = Hotel::query()->updateOrCreate(['slug' => $slug], [
            'name' => $this->option('nama'), 'database' => $db, 'storage_dir' => (string) $this->option('storage'), 'status' => 'active',
        ]);
        $this->info("Terdaftar: {$hotel->slug} → database {$hotel->database}, berkas di storage/app/public/".($hotel->storage_dir ?: '(utama)'));

        return self::SUCCESS;
    }

    private function migrateAll(HotelManager $tenancy): int
    {
        $code = Artisan::call('migrate', ['--force' => true, '--database' => config('tenancy.central_connection'), '--path' => 'database/migrations/central'], $this->output);
        $failed = $code === 0 ? 0 : 1;
        foreach (Hotel::query()->orderBy('id')->get() as $hotel) {
            $r = $tenancy->migrate($hotel);
            $this->line("{$hotel->slug}: ".($r['ok'] ? 'ok' : 'GAGAL').($r['output'] !== '' && ! str_contains($r['output'], 'Nothing to migrate') ? "\n".$r['output'] : ''));
            $failed += $r['ok'] ? 0 : 1;
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function setStatus(): int
    {
        $hotel = Hotel::query()->where('slug', $this->argument('kode'))->first();
        if (! $hotel || ! in_array($this->option('status'), ['active', 'suspended'], true)) {
            return $this->failWith('Pakai: sangatv:hotel status <kode> --status=active|suspended');
        }
        $hotel->update(['status' => $this->option('status')]);
        $this->info("{$hotel->slug}: {$hotel->status}");

        return self::SUCCESS;
    }

    private function failWith(string $message): int
    {
        $this->error($message);

        return self::FAILURE;
    }
}
