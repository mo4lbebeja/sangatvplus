<?php

namespace App\Console\Commands;

use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Enums\DeviceStatus;
use App\Models\AppRelease;
use App\Models\Central\Hotel;
use App\Models\Device;
use App\Support\Audit;
use App\Tenancy\HotelManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/** Used by tv-app/scripts/rilis-ota.sh after the APK was uploaded to storage/app/private/releases. */
class PublishReleaseCommand extends Command
{
    protected $signature = 'sangatv:rilis {file : nama file di storage/app/private/releases}
        {--paket=com.sangatv.plus} {--code=} {--versi=} {--catatan=} {--sha256= : checksum dari laptop (wajib cocok)}
        {--kirim : minta TV dengan paket ini (yang tidak ditahan) update sekarang}';

    protected $description = 'Terbitkan APK yang sudah diunggah sebagai rilis OTA aktif';

    public function handle(HotelManager $tenancy): int
    {
        $path = 'releases/'.basename($this->argument('file'));
        if (! Storage::disk('local')->exists($path)) {
            $this->error("File {$path} tidak ditemukan");

            return self::FAILURE;
        }
        $sha = hash_file('sha256', Storage::disk('local')->path($path));
        if ($this->option('sha256') && ! hash_equals(strtolower($this->option('sha256')), $sha)) {
            $this->error('Checksum tidak cocok: unggahan rusak/terpotong. Ulangi unggah.');

            return self::FAILURE;
        }
        $package = (string) $this->option('paket');
        if (! array_key_exists($package, AppRelease::PACKAGES)) {
            $this->error("Paket {$package} tidak dikenal");

            return self::FAILURE;
        }

        // Multi-hotel: the same APK becomes the active release in every active hotel's database.
        if ($tenancy->enabled() && ! $tenancy->current()) {
            foreach (Hotel::query()->where('status', 'active')->orderBy('id')->get() as $hotel) {
                $this->line("== {$hotel->slug}");
                $tenancy->run($hotel, fn () => $this->publish($path, $sha, $package));
            }

            return self::SUCCESS;
        }
        $this->publish($path, $sha, $package);

        return self::SUCCESS;
    }

    private function publish(string $path, string $sha, string $package): void
    {
        $release = AppRelease::query()->updateOrCreate(
            ['package_name' => $package, 'version_code' => (int) $this->option('code')],
            ['version_name' => (string) $this->option('versi'), 'apk_path' => $path, 'sha256' => $sha, 'notes' => $this->option('catatan'), 'is_active' => true],
        );
        Audit::log('app_release.published', $release, ['package' => $package]);
        $this->info("Rilis aktif: {$package} {$release->version_name} (code {$release->version_code})");

        if ($this->option('kirim')) {
            $devices = Device::query()->where('status', DeviceStatus::Active)->where('hold_updates', false)
                ->where(fn ($q) => $q->where('app_package', $package)->when($package === AppRelease::DEFAULT_PACKAGE, fn ($q) => $q->orWhereNull('app_package')))
                ->get();
            foreach ($devices as $d) {
                $d->commands()->create(['type' => CommandType::CheckUpdate, 'status' => CommandStatus::Pending]);
            }
            $held = Device::query()->where('status', DeviceStatus::Active)->where('hold_updates', true)->count();
            $this->info("Perintah update dikirim ke {$devices->count()} TV".($held ? " ({$held} TV ditahan, dilewati)" : ''));
        }
    }
}
