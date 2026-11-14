<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Step-by-step guide for installing / configuring TVs. Commands are templates:
 * {s} = "-s <ip>:5555", {ip}, {pkg}, {launcher} are filled in live on the page.
 */
class TechnicianGuide extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Perangkat';

    protected static ?string $navigationLabel = 'Panduan Teknisi';

    protected static ?string $title = 'Panduan Teknisi';

    protected static ?string $slug = 'panduan-teknisi';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.technician-guide';

    public string $ip = '';

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->is_active && ($user->isAdmin() || $user->hasRole(UserRole::It));
    }

    public function mount(): void
    {
        $ip = (string) request()->query('ip', '');
        $this->ip = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? $ip : '';
    }

    public function getSubheading(): ?string
    {
        return 'Isi IP TV di bawah, semua perintah ADB otomatis menyesuaikan. Klik ikon salin di tiap perintah.';
    }

    /** @return array<string, string> */
    public function packages(): array
    {
        return [
            'com.sangatv.plus' => 'Rilis (com.sangatv.plus)',
            'com.sangatv.plus.staging' => 'Staging / uji lapangan (com.sangatv.plus.staging)',
            'com.sangatv.plus.debug' => 'Debug (com.sangatv.plus.debug)',
        ];
    }

    /** @return array<string, string> */
    public function launchers(): array
    {
        return [
            'com.google.android.tvlauncher' => 'Google TV / Android TV (Sharp, Coocaa, TCL)',
            'com.google.android.apps.tv.launcherx' => 'Google TV versi baru (launcherx)',
            'com.tcl.tvlauncher' => 'TCL launcher',
        ];
    }

    /**
     * @return list<array{id: string, title: string, icon: Heroicon, intro: string, items: list<array{label: string, cmd?: string, note?: string, warn?: bool}>}>
     */
    public function sections(): array
    {
        return [
            [
                'id' => 'persiapan', 'title' => 'Persiapan TV & laptop', 'icon' => Heroicon::OutlinedWrenchScrewdriver,
                'intro' => 'Sekali per TV. Laptop dan TV harus di jaringan yang sama (bukan Wi-Fi tamu / port terisolasi).',
                'items' => [
                    ['label' => 'Google TV (Sharp, Coocaa): Pengaturan → Sistem → Tentang → klik "Android TV OS build" 7× → Opsi pengembang → aktifkan USB debugging.'],
                    ['label' => 'Android TV (TCL): Pengaturan → Preferensi Perangkat → Tentang → klik "Build" 7× → Opsi pengembang → aktifkan USB debugging.'],
                    ['label' => 'Catat IP TV: Pengaturan → Jaringan & Internet → koneksi aktif → Alamat IP. Isi di kolom IP di atas.'],
                    ['label' => 'Laptop perlu ADB (Android platform-tools). Cek terpasang:', 'cmd' => 'adb version'],
                ],
            ],
            [
                'id' => 'sambung', 'title' => 'Sambungkan ke TV', 'icon' => Heroicon::OutlinedLink,
                'intro' => 'Saat pertama kali, TV menampilkan "Izinkan debugging?" → centang Selalu izinkan → OK.',
                'items' => [
                    ['label' => 'Sambungkan', 'cmd' => 'adb connect {ip}:5555'],
                    ['label' => 'Bila TV memakai "Wireless debugging" dengan kode pairing (ganti PORT & KODE sesuai layar TV)', 'cmd' => 'adb pair {ip}:PORT KODE'],
                    ['label' => 'Pastikan status "device" (bukan "unauthorized")', 'cmd' => 'adb devices -l'],
                    ['label' => 'Cek model & versi Android', 'cmd' => 'adb {s} shell "getprop ro.product.model; getprop ro.build.version.release"'],
                ],
            ],
            [
                'id' => 'pasang', 'title' => 'Pasang & daftarkan aplikasi', 'icon' => Heroicon::OutlinedArrowDownTray,
                'intro' => 'APK diambil dari tim IT (atau menu Update Aplikasi). Kode pendaftaran: CMS → Kamar → ikon QR "Kode daftar TV".',
                'items' => [
                    ['label' => 'Pasang / perbarui APK', 'cmd' => 'adb {s} install -r sangatv-plus.apk'],
                    ['label' => 'Buka aplikasi', 'cmd' => 'adb {s} shell am start -n {pkg}/com.sangatv.plus.MainActivity'],
                    ['label' => 'Ketik kode pendaftaran dari laptop (tanpa tanda strip, ganti KODE)', 'cmd' => 'adb {s} shell input text KODE'],
                    ['label' => 'Kirim tombol OK / selesai', 'cmd' => 'adb {s} shell input keyevent KEYCODE_ENTER'],
                ],
            ],
            [
                'id' => 'siapkan', 'title' => 'Mulai otomatis & izin', 'icon' => Heroicon::OutlinedBolt,
                'intro' => 'Agar SangaTV Plus langsung tampil saat TV dinyalakan (TV tanpa Device Owner). Jalankan berurutan.',
                'items' => [
                    ['label' => 'Jadikan launcher utama (HOME)', 'cmd' => 'adb {s} shell cmd role add-role-holder android.app.role.HOME {pkg} 0'],
                    ['label' => 'Izin tampil di atas aplikasi lain (cadangan auto-start)', 'cmd' => 'adb {s} shell appops set {pkg} SYSTEM_ALERT_WINDOW allow'],
                    ['label' => 'Izin instal update (OTA)', 'cmd' => 'adb {s} shell appops set {pkg} REQUEST_INSTALL_PACKAGES allow'],
                    ['label' => 'Izin notifikasi layanan latar', 'cmd' => 'adb {s} shell pm grant {pkg} android.permission.POST_NOTIFICATIONS'],
                    ['label' => 'Izin ubah pengaturan sistem (durasi screensaver dari CMS → Profil Hotel)', 'cmd' => 'adb {s} shell appops set {pkg} WRITE_SETTINGS allow'],
                    ['label' => 'Tidak dibatasi baterai', 'cmd' => 'adb {s} shell dumpsys deviceidle whitelist +{pkg}'],
                    ['label' => 'Nonaktifkan launcher bawaan (wajib agar tidak tampil duluan)', 'cmd' => 'adb {s} shell pm disable-user --user 0 {launcher}'],
                    ['label' => 'Nonaktifkan HotelTV bila terpasang (agar tidak berebut saat boot)', 'cmd' => 'adb {s} shell pm disable-user --user 0 com.sijalucom.hoteltv'],
                    ['label' => 'Atau semua sekaligus dengan skrip (Mac, folder tv-app)', 'cmd' => './scripts/siapkan-tv.sh {ip}'],
                ],
            ],
            [
                'id' => 'uji', 'title' => 'Uji & periksa', 'icon' => Heroicon::OutlinedCheckBadge,
                'intro' => 'Setelah restart, SangaTV Plus harus tampil sendiri. Status juga terlihat di TV Terdaftar → kolom "Mulai otomatis".',
                'items' => [
                    ['label' => 'Restart TV', 'cmd' => 'adb {s} reboot'],
                    ['label' => 'Cek pemegang HOME (harus {pkg})', 'cmd' => 'adb {s} shell cmd role get-role-holders android.app.role.HOME'],
                    ['label' => 'Aplikasi yang sedang tampil', 'cmd' => 'adb {s} shell "dumpsys activity activities | grep -m1 topResumedActivity"'],
                    ['label' => 'Daftar aplikasi yang dinonaktifkan', 'cmd' => 'adb {s} shell pm list packages -d'],
                    ['label' => 'Ambil screenshot layar TV (tersimpan sebagai tv.png)', 'cmd' => 'adb {s} shell screencap -p /sdcard/tv.png && adb {s} pull /sdcard/tv.png'],
                ],
            ],
            [
                'id' => 'pulihkan', 'title' => 'Kembalikan ke launcher bawaan', 'icon' => Heroicon::OutlinedArrowUturnLeft,
                'intro' => 'Tanpa laptop: Menu teknisi di TV → "Pulihkan launcher bawaan" → Enable. Lewat ADB:',
                'items' => [
                    ['label' => 'Aktifkan lagi launcher bawaan', 'cmd' => 'adb {s} shell pm enable {launcher}'],
                    ['label' => 'Kembalikan HOME ke launcher bawaan', 'cmd' => 'adb {s} shell cmd role add-role-holder android.app.role.HOME {launcher} 0'],
                    ['label' => 'Aktifkan lagi HotelTV (bila perlu)', 'cmd' => 'adb {s} shell pm enable com.sijalucom.hoteltv'],
                    ['label' => 'Atau dengan skrip (Mac)', 'cmd' => './scripts/siapkan-tv.sh {ip} pulihkan'],
                    ['label' => 'Untuk kembali lagi ke SangaTV Plus, ulangi bagian "Mulai otomatis & izin". Launcher bawaan hanya bisa dinonaktifkan lewat ADB (Pengaturan Android tidak menyediakan Disable untuk launcher).', 'warn' => true],
                ],
            ],
            [
                'id' => 'owner', 'title' => 'Device Owner (TV kamar tamu)', 'icon' => Heroicon::OutlinedShieldCheck,
                'intro' => 'Paling kuat: kiosk penuh, HOME terkunci, launcher bisa disembunyikan dari CMS. Syarat: TV baru / reset pabrik, setup TANPA login akun Google.',
                'items' => [
                    ['label' => 'Pastikan tidak ada akun (harus 0)', 'cmd' => 'adb {s} shell "dumpsys account | grep -c \'Account {\'"'],
                    ['label' => 'Jadikan Device Owner (setelah APK terpasang)', 'cmd' => 'adb {s} shell dpm set-device-owner {pkg}/com.sangatv.plus.device.SangaDeviceAdminReceiver'],
                    ['label' => 'Periksa', 'cmd' => 'adb {s} shell dpm list-owners'],
                    ['label' => 'Device Owner hanya bisa dilepas dengan reset pabrik. Lakukan setelah aplikasi siap.', 'warn' => true],
                ],
            ],
            [
                'id' => 'diagnosa', 'title' => 'Diagnosa masalah', 'icon' => Heroicon::OutlinedBugAnt,
                'intro' => 'Kirim hasil perintah ini ke tim IT bila ada kendala.',
                'items' => [
                    ['label' => 'Error / crash aplikasi terbaru', 'cmd' => 'adb {s} shell "logcat -d -t 500 | grep -iE \'sangatv|FATAL|AndroidRuntime\'"'],
                    ['label' => 'Versi aplikasi terpasang', 'cmd' => 'adb {s} shell "dumpsys package {pkg} | grep -m1 versionName"'],
                    ['label' => 'Status izin overlay & instal', 'cmd' => 'adb {s} shell "appops get {pkg} SYSTEM_ALERT_WINDOW; appops get {pkg} REQUEST_INSTALL_PACKAGES"'],
                    ['label' => 'RAM & penyimpanan TV', 'cmd' => 'adb {s} shell "head -2 /proc/meminfo; df -h /data"'],
                    ['label' => 'Durasi screensaver saat ini (milidetik; 1800000 = 30 menit)', 'cmd' => 'adb {s} shell settings get system screen_off_timeout'],
                ],
            ],
            [
                'id' => 'selesai', 'title' => 'Selesai: amankan TV', 'icon' => Heroicon::OutlinedLockClosed,
                'intro' => 'Sesuai kebijakan keamanan: ADB dimatikan setelah TV siap dipakai tamu.',
                'items' => [
                    ['label' => 'Putuskan sambungan', 'cmd' => 'adb disconnect {ip}:5555'],
                    ['label' => 'Matikan ADB di TV (TV Device Owner: Menu teknisi → Kunci perangkat)', 'cmd' => 'adb {s} shell settings put global adb_enabled 0'],
                    ['label' => 'Setelah ADB dimatikan, untuk perawatan berikutnya aktifkan lagi USB debugging dari Opsi pengembang di TV.', 'warn' => true],
                ],
            ],
        ];
    }
}
