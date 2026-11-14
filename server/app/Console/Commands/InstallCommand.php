<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\HotelProfile;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * First-time production setup: creates the first admin with a random password
 * and sets a random technician PIN. Credentials are printed once — never stored in plain text.
 */
class InstallCommand extends Command
{
    protected $signature = 'sangatv:install
        {email : Email admin pertama}
        {--name=Administrator : Nama admin}
        {--hotel= : Nama hotel (opsional)}';

    protected $description = 'Buat admin pertama & PIN teknisi acak (production)';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Email tidak valid.');

            return self::FAILURE;
        }
        if (User::query()->where('email', $email)->exists()) {
            $this->error("Pengguna {$email} sudah ada. Ubah kata sandi lewat CMS → Pengguna CMS.");

            return self::FAILURE;
        }

        // Letters + digits, no look-alikes; satisfies the CMS password rule (≥10, letters & numbers).
        $password = Str::password(16, symbols: false);
        if (! preg_match('/\d/', $password) || ! preg_match('/[a-z]/i', $password)) {
            $password = substr($password, 0, 14).random_int(10, 99);
        }

        User::query()->create([
            'name' => (string) $this->option('name'),
            'email' => $email,
            'password' => $password,
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);

        $hotel = HotelProfile::current();
        if ($this->option('hotel')) {
            $hotel->name = (string) $this->option('hotel');
        }
        $pin = null;
        if (empty($hotel->technician_pin)) {
            do {
                $pin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            } while (in_array($pin, ['000000', '111111', '123456', '654321', '121212'], true));
            $hotel->setTechnicianPin($pin);
        }
        $hotel->save();

        $this->newLine();
        $this->info('SangaTV Plus siap. Simpan kredensial ini — tidak akan ditampilkan lagi:');
        $this->table(['', ''], array_filter([
            ['Login CMS', rtrim((string) config('app.url'), '/').'/admin'],
            ['Email', $email],
            ['Kata sandi', $password],
            $pin ? ['PIN menu teknisi TV', $pin] : null,
        ]));
        $this->warn('Segera ganti kata sandi & PIN setelah login (CMS → Pengguna CMS / Profil Hotel).');

        return self::SUCCESS;
    }
}
