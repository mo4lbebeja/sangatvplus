<?php

namespace App\Console\Commands;

use App\Models\Central\VendorUser;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/** Sijalucom staff account for the vendor panel (/sijalucom). */
class VendorUserCommand extends Command
{
    protected $signature = 'sangatv:vendor {email} {--nama= : nama staf} {--reset : buat kata sandi baru & hapus 2FA} {--nonaktif}';

    protected $description = 'Buat / reset akun panel vendor Sijalucom';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Email tidak valid.');

            return self::FAILURE;
        }
        $user = VendorUser::query()->where('email', $email)->first();

        if ($this->option('nonaktif')) {
            $user?->update(['is_active' => false]);
            $this->info($user ? "{$email} dinonaktifkan." : 'Akun tidak ditemukan.');

            return self::SUCCESS;
        }
        if ($user && ! $this->option('reset')) {
            $this->error('Akun sudah ada. Pakai --reset untuk kata sandi baru.');

            return self::FAILURE;
        }

        $password = Str::password(16, symbols: false);
        $user ??= new VendorUser(['email' => $email, 'name' => $this->option('nama') ?: Str::before($email, '@')]);
        $user->fill(['password' => $password, 'is_active' => true]);
        $user->app_authentication_secret = null;
        $user->app_authentication_recovery_codes = null;
        $user->save();

        $this->info('Panel vendor: '.url('/sijalucom')." · {$email} · kata sandi sementara: {$password}");
        $this->line('Saat login pertama wajib memasang verifikasi dua langkah (Google Authenticator).');

        return self::SUCCESS;
    }
}
