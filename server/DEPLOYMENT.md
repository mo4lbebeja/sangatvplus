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
