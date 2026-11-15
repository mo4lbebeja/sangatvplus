<?php

namespace App\Tenancy;

use App\Enums\UserRole;
use App\Models\Central\Hotel;
use App\Models\HotelProfile;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Multi-hotel: one database per hotel (same pattern as kulakku's RestoManager).
 * - initialize()/end()/run(): point the main connection, cache prefix and public files at one hotel.
 * - provision(): create a new hotel database (schema + profile + admin account).
 */
class HotelManager
{
    private ?Hotel $current = null;

    private ?array $original = null;

    public function enabled(): bool
    {
        return (bool) config('tenancy.enabled');
    }

    public function current(): ?Hotel
    {
        return $this->current;
    }

    private function connection(): string
    {
        return config('tenancy.tenant_connection');
    }

    public static function validSlug(string $slug): bool
    {
        return (bool) preg_match('/^[a-z0-9][a-z0-9-]{1,38}[a-z0-9]$/', $slug)
            && ! in_array($slug, config('tenancy.reserved'), true);
    }

    public static function databaseNameFor(string $slug): string
    {
        return config('tenancy.database_prefix').str_replace('-', '_', $slug);
    }

    /** "Kode Hotel" lookup, case-insensitive. */
    public function findBySlug(?string $slug): ?Hotel
    {
        $slug = Str::lower(trim((string) $slug));

        return $slug === '' ? null : Hotel::query()->where('slug', $slug)->first();
    }

    /** Hotel for TVs/PMS that send no hotel identifier yet (the first hotel). */
    public function legacy(): ?Hotel
    {
        return $this->findBySlug(config('tenancy.legacy_hotel'));
    }

    public function initialize(Hotel $hotel): void
    {
        $conn = $this->connection();
        $this->original ??= [
            'database' => config("database.connections.{$conn}.database"),
            'cache_prefix' => config('cache.prefix'),
            'public_root' => config('filesystems.disks.public.root'),
            'public_url' => config('filesystems.disks.public.url'),
        ];

        config(["database.connections.{$conn}.database" => $hotel->database]);
        DB::purge($conn);
        DB::setDefaultConnection($conn);

        config(['cache.prefix' => 'sangatv_h'.$hotel->id.'_']);
        app('cache')->forgetDriver(config('cache.default'));

        // Public files (logos, slides, gallery …) in a folder per hotel; '' = root (the first hotel, existing files).
        $dir = trim((string) $hotel->storage_dir, '/');
        config([
            'filesystems.disks.public.root' => rtrim($this->original['public_root'], '/').($dir !== '' ? '/'.$dir : ''),
            'filesystems.disks.public.url' => rtrim($this->original['public_url'], '/').($dir !== '' ? '/'.$dir : ''),
        ]);
        Storage::forgetDisk('public');

        $this->current = $hotel;
        app()->instance('hotel', $hotel);
    }

    public function end(): void
    {
        if (! $this->original) {
            return;
        }
        $conn = $this->connection();
        config([
            "database.connections.{$conn}.database" => $this->original['database'],
            'cache.prefix' => $this->original['cache_prefix'],
            'filesystems.disks.public.root' => $this->original['public_root'],
            'filesystems.disks.public.url' => $this->original['public_url'],
        ]);
        DB::purge($conn);
        app('cache')->forgetDriver(config('cache.default'));
        Storage::forgetDisk('public');
        app()->forgetInstance('hotel');
        $this->current = null;
        $this->original = null;
    }

    /** Run a callback inside one hotel's database, then return to the previous hotel (if any). */
    public function run(Hotel $hotel, callable $callback): mixed
    {
        $previous = $this->current;
        $this->initialize($hotel);
        try {
            return $callback($hotel);
        } finally {
            $this->end();
            if ($previous) {
                $this->initialize($previous);
            }
        }
    }

    public function rememberCookie(Hotel $hotel): void
    {
        Cookie::queue(config('tenancy.remember_cookie'), $hotel->slug, 60 * 24 * 365);
    }

    // ------------------------------------------------------------ databases

    public function databaseExists(Hotel $hotel): bool
    {
        $central = DB::connection(config('tenancy.central_connection'));

        return $central->getDriverName() === 'pgsql'
            ? $central->select('SELECT 1 FROM pg_database WHERE datname = ?', [$hotel->database]) !== []
            : $central->select('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?', [$hotel->database]) !== [];
    }

    public function createDatabase(Hotel $hotel): void
    {
        if (! preg_match('/^[a-z0-9_]+$/', $hotel->database)) {
            throw new RuntimeException('Nama database tidak valid.');
        }
        $central = DB::connection(config('tenancy.central_connection'));
        $central->getDriverName() === 'pgsql'
            ? $central->statement("CREATE DATABASE \"{$hotel->database}\" ENCODING 'UTF8'")
            : $central->statement("CREATE DATABASE `{$hotel->database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    public function dropDatabase(Hotel $hotel): void
    {
        if (! preg_match('/^[a-z0-9_]+$/', $hotel->database)) {
            throw new RuntimeException('Nama database tidak valid.');
        }
        $central = DB::connection(config('tenancy.central_connection'));
        $central->getDriverName() === 'pgsql'
            ? $central->statement("DROP DATABASE IF EXISTS \"{$hotel->database}\" WITH (FORCE)")
            : $central->statement("DROP DATABASE IF EXISTS `{$hotel->database}`");
    }

    /** @return array{ok: bool, output: string} */
    public function migrate(Hotel $hotel): array
    {
        return $this->run($hotel, function () {
            $code = Artisan::call('migrate', ['--force' => true, '--database' => $this->connection(), '--path' => 'database/migrations']);

            return ['ok' => $code === 0, 'output' => trim(Artisan::output())];
        });
    }

    /**
     * New hotel: central record, its own database with the full schema, hotel profile and the first admin
     * account. Returns the admin's one-time password.
     */
    public function provision(string $slug, string $name, string $adminEmail, ?string $adminName = null): array
    {
        $slug = Str::lower(trim($slug));
        if (! self::validSlug($slug)) {
            throw new RuntimeException('Kode hotel hanya huruf kecil, angka, dan tanda minus (3–40 karakter).');
        }
        if (Hotel::query()->where('slug', $slug)->exists()) {
            throw new RuntimeException("Kode hotel {$slug} sudah dipakai.");
        }

        $hotel = Hotel::query()->create([
            'slug' => $slug,
            'name' => $name,
            'database' => self::databaseNameFor($slug),
            'storage_dir' => 'hotels/'.$slug,
            'status' => 'active',
            'contact_email' => $adminEmail,
        ]);

        if (! $this->databaseExists($hotel)) {
            $this->createDatabase($hotel);
        }
        $result = $this->migrate($hotel);
        if (! $result['ok']) {
            throw new RuntimeException('Migrasi database hotel gagal: '.$result['output']);
        }

        $password = Str::password(16, symbols: false);
        $this->run($hotel, function () use ($name, $adminEmail, $adminName, $password) {
            HotelProfile::current()->update(['name' => $name]);
            User::query()->create([
                'name' => $adminName ?: 'Admin '.$name,
                'email' => $adminEmail,
                'password' => $password,
                'role' => UserRole::Admin,
                'is_active' => true,
            ]);
        });

        return ['hotel' => $hotel, 'password' => $password];
    }
}
