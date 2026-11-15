#!/usr/bin/env bash
# Terbitkan APK ke TV lewat OTA.
#   ./scripts/rilis-ota.sh release "Catatan rilis" [--kirim]     build release (com.sangatv.plus)
#   ./scripts/rilis-ota.sh staging "Catatan rilis" [--kirim]     build staging (com.sangatv.plus.staging)
# --kirim : TV dengan paket yang sama (dan tidak "Tahan update") diminta update sekarang.
set -euo pipefail
cd "$(dirname "$0")/.."
VARIANT="${1:?Pakai: rilis-ota.sh release|staging \"catatan\" [--kirim]}"; NOTES="${2:-}"; PUSH="${3:-}"
HOST="hotel-tv-vps"; DIR="/www/wwwroot/sangatvplus.sijalucom.online"
export JAVA_HOME="/Applications/Android Studio.app/Contents/jbr/Contents/Home"
BT=$(ls -d "$HOME"/Library/Android/sdk/build-tools/*/ | sort -V | tail -1)

if [[ "$VARIANT" == "release" ]]; then TASK=assembleRelease; APK=app/build/outputs/apk/release/app-release.apk
else TASK=assembleStaging; APK=app/build/outputs/apk/staging/app-staging.apk; fi

echo "==> Build $VARIANT"
./gradlew -q ":app:$TASK" -Psangatv.debugApiBaseUrl=https://sangatvplus.sijalucom.online/
BADGE=$("$BT/aapt2" dump badging "$APK" | head -1)
PKG=$(sed -E "s/.*name='([^']+)'.*/\1/" <<<"$BADGE"); CODE=$(sed -E "s/.*versionCode='([0-9]+)'.*/\1/" <<<"$BADGE"); NAME=$(sed -E "s/.*versionName='([^']+)'.*/\1/" <<<"$BADGE")
SHA=$(shasum -a 256 "$APK" | cut -d' ' -f1)
FILE="sangatv-plus-$NAME-$VARIANT.apk"
echo "    $PKG $NAME (code $CODE) sha256 ${SHA:0:16}"

echo "==> Unggah (bisa dilanjutkan bila koneksi putus)"
for i in 1 2 3 4 5; do
    rsync -a --partial --inplace --timeout=60 "$APK" "$HOST:$DIR/storage/app/private/releases/$FILE" && break
    echo "    terputus, coba lagi ($i)…"; sleep 5
done
ssh "$HOST" "chown www:www $DIR/storage/app/private/releases/$FILE && cd $DIR && sudo -u www php artisan sangatv:rilis $FILE --paket=$PKG --code=$CODE --versi=$NAME --sha256=$SHA --catatan=$(printf %q "$NOTES") $PUSH"
