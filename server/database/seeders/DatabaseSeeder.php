<?php

namespace Database\Seeders;

use App\Enums\FacilityCategory;
use App\Enums\SourceType;
use App\Enums\UserRole;
use App\Models\AppWhitelist;
use App\Models\Channel;
use App\Models\ChannelCategory;
use App\Models\Facility;
use App\Models\HotelProfile;
use App\Models\Room;
use App\Models\Slide;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Development data only. Never run against production
     * (default passwords and PIN below).
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new \RuntimeException('DatabaseSeeder berisi data contoh & kata sandi default — tidak boleh dijalankan di production. Gunakan: php artisan sangatv:install <email>');
        }

        foreach ([
            ['Admin', 'admin@sangatv.test', UserRole::Admin],
            ['Front Office', 'fo@sangatv.test', UserRole::FrontOffice],
            ['IT', 'it@sangatv.test', UserRole::It],
        ] as [$name, $email, $role]) {
            User::query()->updateOrCreate(['email' => $email], ['name' => $name, 'password' => 'password', 'role' => $role, 'is_active' => true]);
        }

        $hotel = HotelProfile::current();
        $hotel->fill([
            'name' => 'Hotel Demo',
            'tagline' => 'Stay & Relax',
            'about' => 'Hotel 96 kamar di pusat kota. Ganti teks ini dari CMS.',
            'address' => 'Jl. Contoh No. 1',
            'reception_phone' => '+62 22 000000',
            'reception_extension' => '0',
            'wifi_ssid' => 'HotelDemo-Guest',
            'wifi_password' => 'selamatdatang',
            'check_out_info' => 'Check-out paling lambat pukul 12.00. Hubungi resepsionis untuk late check-out.',
            'latitude' => -6.9175,
            'longitude' => 107.6191,
        ]);
        $hotel->setTechnicianPin('123456');
        $hotel->save();

        // 4 floors × 24 rooms = 96 rooms: 101–124 … 401–424
        foreach (range(1, 4) as $floor) {
            foreach (range(1, 24) as $i) {
                Room::query()->firstOrCreate(
                    ['number' => (string) ($floor * 100 + $i)],
                    ['floor' => $floor, 'type' => $i <= 4 ? 'Suite' : 'Deluxe'],
                );
            }
        }

        $cats = collect(['Nasional', 'Berita', 'Olahraga', 'Anak', 'Hotel'])
            ->mapWithKeys(fn ($name, $i) => [$name => ChannelCategory::query()->firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'sort_order' => ($i + 1) * 10],
            )]);

        $channels = [
            [101, 'TVRI Nasional', 'Nasional'],
            [102, 'Kompas TV', 'Berita'],
            [103, 'Metro TV', 'Berita'],
            [104, 'TVRI Sport', 'Olahraga'],
            [105, 'RTV', 'Anak'],
        ];
        foreach ($channels as [$num, $name, $cat]) {
            Channel::query()->firstOrCreate(['number' => $num], [
                'name' => $name,
                'channel_category_id' => $cats[$cat]->id,
                'source_type' => SourceType::LocalMulticast,
                'url' => 'udp://239.1.1.'.$num.':1234',
                'sort_order' => $num,
            ]);
        }
        // Public test stream so the TV app can be tried before encoders exist.
        Channel::query()->firstOrCreate(['number' => 900], [
            'name' => 'Hotel Info (uji HLS)',
            'channel_category_id' => $cats['Hotel']->id,
            'source_type' => SourceType::LocalHls,
            'url' => 'https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8',
            'sort_order' => 900,
        ]);

        $slides = [
            ['Selamat datang', 'NIKMATI MASA INAP ANDA', 'Semua yang Anda butuhkan ada di layar ini.', [6, 26, 43], [56, 198, 217]],
            ['Restoran', 'SARAPAN 06.00 – 10.00', 'Buffet sarapan di lantai 1, restoran utama.', [8, 34, 58], [240, 168, 90]],
            ['Kolam renang', 'SEGARKAN HARI ANDA', 'Kolam renang lantai 4, buka 06.00 – 21.00.', [3, 18, 32], [90, 160, 240]],
        ];
        foreach ($slides as $i => [$kicker, $title, $desc, $from, $to]) {
            $path = "slides/demo-{$i}.jpg";
            $this->placeholderImage($path, $from, $to);
            Slide::query()->firstOrCreate(['title' => $title], [
                'kicker' => $kicker, 'description' => $desc, 'image_path' => $path, 'sort_order' => $i * 10,
            ]);
        }

        $facilities = [
            [FacilityCategory::Facility, 'Kolam Renang', 'Lantai 4', '06.00 – 21.00'],
            [FacilityCategory::Facility, 'Pusat Kebugaran', 'Lantai 3', '24 jam'],
            [FacilityCategory::Facility, 'Ruang Rapat', 'Lantai 2', 'Sesuai reservasi'],
            [FacilityCategory::Dining, 'Restoran Utama', 'Lantai 1', '06.00 – 22.00'],
            [FacilityCategory::Dining, 'Room Service', 'Tekan 1 di telepon kamar', '24 jam'],
        ];
        foreach ($facilities as $i => [$cat, $name, $loc, $hours]) {
            Facility::query()->firstOrCreate(['name' => $name], [
                'category' => $cat, 'location' => $loc, 'hours' => $hours, 'sort_order' => $i * 10,
            ]);
        }

        foreach ([
            ['com.google.android.youtube.tv', 'YouTube'],
            ['com.netflix.ninja', 'Netflix'],
            ['com.disney.disneyplus', 'Disney+ Hotstar'],
        ] as $i => [$pkg, $label]) {
            AppWhitelist::query()->firstOrCreate(['package_name' => $pkg], ['label' => $label, 'sort_order' => $i * 10]);
        }
    }

    /** 1920×1080 gradient JPEG so the launcher has something to show. */
    private function placeholderImage(string $path, array $from, array $to): void
    {
        if (Storage::disk('public')->exists($path) || ! function_exists('imagecreatetruecolor')) {
            return;
        }
        $w = 1920;
        $h = 1080;
        $img = imagecreatetruecolor($w, $h);
        for ($x = 0; $x < $w; $x += 4) {
            $t = $x / $w;
            $c = imagecolorallocate($img, ...array_map(fn ($a, $b) => (int) ($a + ($b - $a) * $t * 0.55), $from, $to));
            imagefilledrectangle($img, $x, 0, $x + 3, $h, $c);
        }
        ob_start();
        imagejpeg($img, null, 80);
        Storage::disk('public')->put($path, ob_get_clean());
    }
}
