# Deploy produksi — SangaTV Plus

| | |
|---|---|
| URL | https://sangatvplus.sijalucom.online (CMS: `/admin`, API TV: `/api/v1`) |
| Server | `hotel-tv-vps` (aaPanel, Ubuntu 24.04, PHP 8.4, Nginx, MariaDB 10.11) |
| Folder | `/www/wwwroot/sangatvplus.sijalucom.online` (Nginx root → `public/`) |
| Database | MariaDB `db_sangatvplus` (kredensial hanya di `.env` server) |

## Deploy rutin (dari laptop)

```bash
cd server && ./deploy.sh
```
Menjalankan test lokal → backup DB → rsync kode → `composer install` → migrasi → aset Filament → cache → verifikasi.
`.env`, `storage/`, `.user.ini`, `.well-known` dan berkas aaPanel tidak pernah ditimpa/dihapus.

## Pertama kali (sudah dilakukan 15 Nov 2026)

1. aaPanel: Add site + SSL Let's Encrypt; Nginx root → `public`, rewrite Laravel, force HTTPS.
2. `.env` produksi dibuat langsung di server (`APP_ENV=production`, `APP_DEBUG=false`, `DB_CONNECTION=mariadb`,
   `SESSION_SECURE_COOKIE=true`, `WEATHER_ENABLED=false`).
3. `./deploy.sh`
4. Admin pertama: `php artisan sangatv:install <email>` (kata sandi & PIN teknisi acak, ditampilkan sekali).
   `db:seed` diblokir di produksi (berisi data contoh & kata sandi default).

## Aplikasi TV ke produksi

```bash
cd tv-app
ANDROID_SERIAL=<IP-TV>:5555 ./gradlew :app:installStaging -Psangatv.debugApiBaseUrl=https://sangatvplus.sijalucom.online/
./scripts/siapkan-tv.sh <IP-TV>
```
Rilis final (`assembleRelease`) butuh: keystore, `sangatv.releaseApiBaseUrl=https://sangatvplus.sijalucom.online/`,
2 pin sertifikat (`sangatv.certPins`), dan CA di `app/src/release/res/raw/internal_ca.pem`.

## Catatan
- Cuaca dimatikan: Open-Meteo gratis hanya untuk non-komersial.
- Imagick belum terpasang di VPS; pengecil gambar memakai GD (aman dengan `memory_limit` 256M).
  Pasang ekstensi imagick di aaPanel untuk foto kamera sangat besar.
- **Email laporan uptime mingguan** (Senin 07.00) baru terkirim setelah SMTP diisi di `.env` produksi
  (`MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`), lalu
  `php artisan config:cache`. Sampai itu, laporan tetap bisa dilihat di CMS → Statistik & Uptime.
- **EPG** disinkron cron tiap 6 jam (`sangatv:sync-epg`). Manual: CMS → Channel → *Sinkron EPG*.
- **Update aplikasi TV (OTA)**: build APK → CMS → Update Aplikasi → *Unggah rilis* (aktifkan) → *Minta semua TV update sekarang*.
  TV yang menyala mengunduh dalam ±1 menit (tanpa tombol ini: cek otomatis tiap 6 jam). TV tanpa Device Owner menampilkan
  dialog "Update?" yang perlu dikonfirmasi; TV Device Owner memasang diam-diam.

## Masa layanan & Mode lepas (akhir kontrak)
- Atur masa berlaku (oleh Sijalucom, tidak bisa diubah pengguna hotel di CMS):
  `php artisan sangatv:layanan --sampai=2027-12-31` · tanpa batas: `--sampai=tanpa-batas`
- 14 hari sebelum berakhir, semua halaman CMS menampilkan banner peringatan.
- Saat berakhir (atau `--lepas`, atau CMS → Profil Hotel → *Lepaskan semua TV*), setiap TV dalam ±1 menit masuk
  **Mode lepas**: berhenti jadi layar utama, memulihkan pengaturan TV, menampilkan peluncur netral (semua aplikasi, HDMI,
  Pengaturan, *Hapus SangaTV Plus*). CMS → TV Terdaftar kolom **Mode** menunjukkan "Dilepas" per TV.
- Batalkan: `--aktifkan` atau tombol *Batalkan pelepasan* → TV kembali ke layar hotel otomatis.
- TV yang **30 hari** tidak bisa menghubungi server (server dimatikan) juga lepas sendiri, dan pulih otomatis bila server kembali.
- TV yang dicabut aksesnya (TV Terdaftar → *Cabut akses*) tidak lagi terjebak di layar pendaftaran; teknisi bisa
  *Daftarkan TV* ulang dari layar netralnya.
- **Ekspor data hotel** (ZIP berisi CSV, tanpa rahasia): CMS → Profil Hotel → *Ekspor data*.

## Operasional (backup, peringatan, privasi, 2FA)
Isi di `.env` produksi lalu `sudo -u www php artisan config:cache`:
```
TELEGRAM_BOT_TOKEN=...        # bot dari @BotFather
TELEGRAM_CHAT_ID=...          # chat/grup penerima
ALERT_EMAILS=it@hotel.com     # tambahan selain Profil Hotel → Email laporan
MAIL_MAILER=smtp + MAIL_HOST/PORT/USERNAME/PASSWORD/FROM_ADDRESS
BACKUP_PASSWORD=...           # sudah diisi; salinan di laptop ~/.sangatv/backup-INFO.txt
GUEST_RETENTION_DAYS=90       # data tamu check-out dianonimkan setelah N hari
```
- **Backup** 02.30 WIB: database (harian) + media (Minggu), AES-256, 14 terakhir di `storage/app/private/backups`,
  salinan ke Telegram. Manual: `php artisan sangatv:backup --media`. Pulihkan: lihat `~/.sangatv/backup-INFO.txt`.
- **Peringatan** tiap 5 menit (`sangatv:monitor`): channel gagal 3× berturut-turut, TV tanpa sinyal > 24 jam; dikirim
  sekali saat bermasalah dan sekali saat pulih. Uji: CMS → Statistik & Uptime → *Kirim notifikasi uji*.
  Server mati harus dipantau dari luar: daftarkan `https://sangatvplus.sijalucom.online/up` di UptimeRobot (gratis).
- **Privasi tamu** 03.15 WIB (`sangatv:privasi-tamu`).
- **2FA**: akun admin wajib mengaktifkan authenticator (Profil → Autentikasi multi-faktor) sebelum bisa memakai CMS.

## Rilis aplikasi TV (OTA)
`tv-app/scripts/rilis-ota.sh release "catatan" --kirim` — build, unggah (dilanjutkan bila putus), cek checksum,
terbitkan untuk paket yang sama, dan kirim ke TV yang **tidak** "Tahan update" (CMS → TV Terdaftar).
Kunci tanda tangan release: `~/.sangatv/sangatv-release.jks` (+ sandi di `keystore-INFO.txt`) — **wajib dibackup**.
