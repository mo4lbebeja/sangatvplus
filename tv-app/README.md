# SangaTV Plus — aplikasi TV (Kotlin)

Kotlin native · Jetpack Compose for TV · Media3 (HLS, DASH+Widevine, UDP multicast) · Retrofit/OkHttp.

## Build

```bash
export JAVA_HOME="/Applications/Android Studio.app/Contents/jbr/Contents/Home"
./gradlew :app:assembleDebug      # app/build/outputs/apk/debug/
```

Debug memanggil API di `sangatv.debugApiBaseUrl` (gradle.properties), default `http://10.0.2.2:8000/`
(`php artisan serve --host 0.0.0.0` di Mac). Untuk TV sungguhan pakai IP server, mis.
`./gradlew assembleDebug -Psangatv.debugApiBaseUrl=http://192.168.10.5:8000/`.

Release **sengaja gagal** sampai ini lengkap:
- `sangatv.releaseApiBaseUrl=https://…` dan `sangatv.certPins=sha256/…,sha256/…` (minimal 2 pin: aktif + cadangan)
- CA internal hotel di `app/src/release/res/raw/internal_ca.pem`
- `keystore.properties` (storeFile, storePassword, keyAlias, keyPassword)

## Pasang di TV kamar

1. Di CMS: **Kamar → (kamar) → Kode daftar TV**. Kode berlaku 30 menit dan cuma bisa dipakai sekali.
2. Pasang APK, buka, lalu masukkan kode. TV terikat ke kamar itu.

### TV nyala → langsung SangaTV Plus

**A. Device Owner (disarankan, paling kuat).** HOME terkunci ke aplikasi ini, launcher bawaan tidak pernah tampil,
kiosk aktif, ADB dimatikan setelah pendaftaran, data aplikasi tamu bisa dihapus saat check-out.
Syarat: TV belum punya akun Google (reset pabrik, lewati login).
```bash
adb install sangatv-plus.apk
adb shell dpm set-device-owner com.sangatv.plus/.device.SangaDeviceAdminReceiver
```

**B. Tanpa Device Owner — pakai skrip (disarankan untuk TV yang sudah login akun Google).**
```bash
./scripts/uji-tv.sh <IP-TV>          # build varian staging + pasang + buka (server laptop)
./scripts/siapkan-tv.sh <IP-TV>      # HOME, izin overlay/OTA/notifikasi/baterai, nonaktifkan launcher bawaan & HotelTV
./scripts/siapkan-tv.sh <IP-TV> pulihkan   # batalkan: launcher bawaan & HotelTV aktif lagi
```
Varian **staging** (`com.sangatv.plus.staging`) dioptimalkan seperti rilis (R8, tidak debuggable) sehingga cepat start
di TV ber-RAM 1 GB, tetapi boleh memakai server http laptop. Gunakan staging untuk uji di TV; debug lambat start
sehingga kalah cepat dari aplikasi bawaan TV saat boot.

Status "Mulai otomatis" tiap TV terlihat di CMS → TV Terdaftar.

**B (manual).** Perintah yang dijalankan skrip, bila perlu satu per satu: Saat boot aplikasi dibuka otomatis, dan bila
launcher bawaan muncul, aplikasi dibuka lagi. Fitur kiosk/hapus data tidak tersedia.
```bash
adb shell appops set com.sangatv.plus SYSTEM_ALERT_WINDOW allow
adb shell settings put secure enabled_accessibility_services com.sangatv.plus/com.sangatv.plus.device.LauncherWatchService
adb shell settings put secure accessibility_enabled 1
adb shell cmd package set-home-activity com.sangatv.plus/.MainActivity   # bila TV mengizinkan
```
Status dan tombol pengaturannya juga ada di **Menu teknisi**.

## Menu teknisi
Di Beranda tekan **▲ ▲ ▼ ▼ ◀ ▶ ◀ ▶**, lalu masukkan PIN 6 digit (diatur di CMS → Profil Hotel).
Salah 5× → terkunci 15 menit. Semua percobaan tercatat di Audit Log, termasuk yang dilakukan saat offline.

## Menonton TV (remote)
| Tombol | Fungsi |
|---|---|
| ▲ ▼ / CH+ CH− | Ganti channel (channel dewasa dilewati selama terkunci) |
| OK / ◀ | Daftar channel (kategori, **★ Favorit**, acara yang sedang tayang) |
| ▶ / MENU | **Opsi**: jadwal acara, favorit, audio, subtitle, channel sebelumnya, matikan TV otomatis |
| GUIDE / tombol merah | Jadwal acara (EPG) |
| INFO | Bar info: acara sekarang + berikutnya |
| LAST | Channel sebelumnya · tombol kuning: favorit |

- **EPG**: dari sumber XMLTV di CMS → Profil Hotel → Channel TV (sinkron tiap 6 jam).
- **Channel dewasa**: minta PIN 4 digit (CMS → Profil Hotel). Terbuka sampai TV standby; salah 5× → kunci 10 menit.
- **Matikan TV otomatis**: perlu izin `WRITE_SECURE_SETTINGS` (`siapkan-tv.sh` memberikannya). Tanpa izin, layar hanya
  menggelap. Pengaturan tidur asli TV disimpan dan dipulihkan otomatis saat TV menyala lagi.
- **Pengumuman** dari CMS tampil di semua layar dalam ±1 menit. Tingkat *Darurat* membawa TV kembali ke SangaTV Plus
  walau tamu sedang di Netflix/YouTube, dan tidak bisa ditutup sampai pengumuman berakhir.
- Data tontonan (channel + durasi per kamar, tanpa nama tamu) dikirim bersama heartbeat untuk CMS → Statistik & Uptime.

## Google TV (mis. Sharp 2K Smart TV-A)
Google TV mengabaikan pilihan layar utama (role HOME) dan menampilkan layar *pemulihan* bila launcher-nya dinonaktifkan.
`siapkan-tv.sh` mendeteksi Google TV: launcher bawaan dibiarkan aktif dan **penjaga layar utama** (layanan aksesibilitas
`LauncherWatchService`) dinyalakan, sehingga setiap tombol HOME kembali ke Beranda SangaTV Plus dalam < 1 detik.
Penguncian penuh (tanpa launcher Google sama sekali) hanya bisa lewat **Device Owner** (TV di-reset tanpa akun Google).
