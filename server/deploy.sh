#!/usr/bin/env bash
#
# Deploy SangaTV Plus (server/) ke VPS produksi. Dijalankan dari laptop.
#
# ATURAN
# 1. .env HANYA ada di server (berisi password DB & APP_KEY) — tidak pernah di repo/rsync.
# 2. JANGAN hapus berkas milik aaPanel / yang hanya ada di server:
#    .user.ini (open_basedir, immutable), .well-known (SSL), 404/502.html, public/.user.ini.
# 3. storage/ hanya dibuat strukturnya, isinya (upload, log, sesi) milik server.
# 4. Cache produksi aktif: deploy selalu re-cache config/route/view.
# 5. JANGAN jalankan db:seed di produksi (diblokir). Admin pertama: php artisan sangatv:install <email>.
set -euo pipefail
cd "$(dirname "$0")"

HOST="hotel-tv-vps"
DIR="/www/wwwroot/sangatvplus.sijalucom.online"
URL="https://sangatvplus.sijalucom.online"

echo "==> Uji lokal dulu"
php artisan test --compact >/dev/null && echo "    test lulus"

echo "==> Backup DB produksi (bila .env sudah ada)"
ssh "$HOST" "cd $DIR 2>/dev/null && [ -f .env ] && mkdir -p /root/backup-sangatvplus && set -a && . ./.env && set +a && mysqldump -u\"\$DB_USERNAME\" -p\"\$DB_PASSWORD\" \"\$DB_DATABASE\" > /root/backup-sangatvplus/db-\$(date +%Y%m%d-%H%M%S).sql || true"

echo "==> Kirim kode"
rsync -az --delete \
  --exclude='.env' --exclude='.env.*' --exclude='.git' --exclude='storage' --exclude='vendor' \
  --exclude='node_modules' --exclude='tests' --exclude='bootstrap/cache/*.php' \
  --exclude='.user.ini' --exclude='public/.user.ini' --exclude='.well-known' \
  --exclude='404.html' --exclude='502.html' --exclude='index.html' --exclude='.htaccess' \
  --exclude='public/storage' --exclude='public/css/filament' --exclude='public/js/filament' --exclude='public/fonts/filament' \
  --exclude='public/vendor' \
  --exclude='deploy.sh' --exclude='.phpunit.cache' \
  ./ "$HOST:$DIR/"

echo "==> Struktur storage (tanpa menimpa isi server)"
rsync -a --include='*/' --include='.gitignore' --exclude='*' storage/ "$HOST:$DIR/storage/"

echo "==> Dependensi, migrasi, aset Filament, cache, ownership"
ssh "$HOST" "cd $DIR && composer install --no-dev --optimize-autoloader --no-interaction --quiet"
ssh "$HOST" "cd $DIR && php artisan migrate --force && php artisan storage:link --quiet 2>/dev/null || true"
# Livewire/Filament JS must exist as real files: aaPanel's nginx serves every *.js as a static file.
ssh "$HOST" "cd $DIR && php artisan livewire:publish --assets --silent && php artisan filament:assets --quiet && php artisan optimize:clear --quiet && php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache"
ssh "$HOST" "chown -R www:www $DIR 2>&1 | grep -v '.user.ini' || true"

echo "==> Verifikasi"
curl -s -o /dev/null -w "  health /up  : HTTP %{http_code}\n" "$URL/up"
curl -s -o /dev/null -w "  login CMS   : HTTP %{http_code}\n" "$URL/admin/login"
LW=$(curl -s "$URL/admin/login" | grep -oE 'src="[^"]*livewire[^"]*\.js[^"]*"' | head -1 | sed -E 's/src="([^"]*)"/\1/')
curl -s -o /dev/null -w "  livewire.js : HTTP %{http_code} (harus 200, kalau tidak login CMS mati)\n" "$LW"
curl -s -o /dev/null -w "  API (tanpa token harus 401): HTTP %{http_code}\n" -H "Accept: application/json" "$URL/api/v1/bootstrap"
