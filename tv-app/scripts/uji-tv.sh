#!/usr/bin/env bash
# Uji SangaTV Plus di TV sungguhan lewat ADB jaringan.
#   ./scripts/uji-tv.sh <IP-TV>[:port]        contoh: ./scripts/uji-tv.sh 192.168.101.45
#   ./scripts/uji-tv.sh selesai              hentikan server & kembalikan APP_URL
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
ADB="$HOME/Library/Android/sdk/platform-tools/adb"
export JAVA_HOME="/Applications/Android Studio.app/Contents/jbr/Contents/Home"
PKG="com.sangatv.plus.staging"
LAPTOP_IP="$(ipconfig getifaddr en0 || ipconfig getifaddr en1)"
API="http://$LAPTOP_IP:8000"

if [[ "${1:-}" == "selesai" ]]; then
    pkill -f "artisan serve --host 0.0.0.0 --port 8000" || true
    sed -i '' 's|^APP_URL=.*|APP_URL=http://sangatv-cms.test|' "$ROOT/server/.env"
    (cd "$ROOT/server" && php artisan config:clear -q)
    echo "Server uji dihentikan, APP_URL dikembalikan."
    exit 0
fi

TV="${1:?Pakai: ./scripts/uji-tv.sh <IP-TV>[:port]}"
[[ "$TV" == *:* ]] || TV="$TV:5555"

echo "1/4  Menghubungkan ke TV $TV … (setujui dialog 'Izinkan debugging' di layar TV)"
"$ADB" connect "$TV"
"$ADB" -s "$TV" wait-for-device
echo "     TV: $("$ADB" -s "$TV" shell getprop ro.product.manufacturer) $("$ADB" -s "$TV" shell getprop ro.product.model), Android $("$ADB" -s "$TV" shell getprop ro.build.version.release)"

echo "2/4  Menyalakan server di $API (bisa dijangkau TV)"
sed -i '' "s|^APP_URL=.*|APP_URL=$API|" "$ROOT/server/.env"
(cd "$ROOT/server" && php artisan config:clear -q)
if ! curl -s -o /dev/null "$API/up"; then
    (cd "$ROOT/server" && nohup php artisan serve --host 0.0.0.0 --port 8000 > /tmp/sangatv-serve.log 2>&1 &)
    for _ in $(seq 1 20); do curl -s -o /dev/null "$API/up" && break; sleep 0.5; done
fi
curl -s -o /dev/null -w "     API /up → %{http_code}\n" "$API/up"

echo "3/4  Build & pasang aplikasi (API = $API/)"
(cd "$ROOT/tv-app" && ANDROID_SERIAL="$TV" ./gradlew -q :app:installStaging -Psangatv.debugApiBaseUrl="$API/")

echo "4/4  Membuka SangaTV Plus di TV"
"$ADB" -s "$TV" shell am start -n "$PKG/com.sangatv.plus.MainActivity" >/dev/null

cat <<MSG

Selesai. Langkah berikutnya:
  • CMS (laptop): http://sangatv-cms.test/admin → Kamar → pilih kamar → ikon QR "Kode daftar TV"
  • Ketik kode di TV (atau dari laptop):  $ADB -s $TV shell input text KODE-TANPA-STRIP
  • Screenshot layar TV:                  $ADB -s $TV exec-out screencap -p > tv.png
  • Log aplikasi:                          $ADB -s $TV logcat --pid=\$($ADB -s $TV shell pidof $PKG)
  • Selesai uji:                           ./scripts/uji-tv.sh selesai
MSG
