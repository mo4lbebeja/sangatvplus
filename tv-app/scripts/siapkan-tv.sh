#!/usr/bin/env bash
# Menyiapkan izin & auto-start SangaTV Plus di TV (tanpa Device Owner), lewat ADB.
#   ./scripts/siapkan-tv.sh <IP-TV>[:port]                 siapkan TV
#   ./scripts/siapkan-tv.sh <IP-TV>[:port] --biarkan-hoteltv   jangan nonaktifkan HotelTV
#   ./scripts/siapkan-tv.sh <IP-TV>[:port] pulihkan        kembalikan launcher bawaan & HotelTV
set -uo pipefail

ADB="$HOME/Library/Android/sdk/platform-tools/adb"
TV="${1:?Pakai: ./scripts/siapkan-tv.sh <IP-TV>[:port] [pulihkan|--biarkan-hoteltv]}"
[[ "$TV" == *:* ]] || TV="$TV:5555"
MODE="${2:-siapkan}"
sh() { "$ADB" -s "$TV" shell "$@" 2>&1 | tr -d '\r'; }

STOCK_LAUNCHERS=(com.google.android.tvlauncher com.google.android.apps.tv.launcherx com.google.android.leanbacklauncher
                 com.android.tvlauncher com.tcl.launcher com.tcl.tvlauncher com.skyworth.launcher com.coocaa.launcher)
OTHER_AUTOSTART=(com.sijalucom.hoteltv)   # aplikasi lain yang ikut berebut tampil saat boot

"$ADB" connect "$TV" >/dev/null
installed() { sh pm list packages "$1" | grep -qx "package:$1"; }

# Paket SangaTV Plus yang terpasang (rilis diutamakan).
PKG=""
for p in com.sangatv.plus com.sangatv.plus.staging com.sangatv.plus.debug; do installed "$p" && { PKG="$p"; break; }; done
[[ -n "$PKG" ]] || { echo "SangaTV Plus belum terpasang di $TV"; exit 1; }

if [[ "$MODE" == "pulihkan" ]]; then
    for l in "${STOCK_LAUNCHERS[@]}"; do installed "$l" && sh pm enable "$l" >/dev/null && echo "  aktif lagi: $l"; done
    for a in "${OTHER_AUTOSTART[@]}"; do installed "$a" && sh pm enable "$a" >/dev/null && echo "  aktif lagi: $a"; done
    for l in "${STOCK_LAUNCHERS[@]}"; do installed "$l" && { sh cmd role add-role-holder android.app.role.HOME "$l" 0 >/dev/null; break; }; done
    echo "Selesai: launcher bawaan dipulihkan."
    exit 0
fi

echo "Menyiapkan $PKG di $TV ($(sh getprop ro.product.manufacturer) $(sh getprop ro.product.model), Android $(sh getprop ro.build.version.release))"

echo "• Launcher utama (HOME)";           sh cmd role add-role-holder android.app.role.HOME "$PKG" 0 >/dev/null
echo "• Tampil di atas aplikasi lain";     sh appops set "$PKG" SYSTEM_ALERT_WINDOW allow
echo "• Instal aplikasi tidak dikenal (OTA)"; sh appops set "$PKG" REQUEST_INSTALL_PACKAGES allow
echo "• Ubah pengaturan sistem (durasi screensaver dari CMS)"; sh appops set "$PKG" WRITE_SETTINGS allow
echo "• Sleep timer tamu (TV standby otomatis)"; sh pm grant "$PKG" android.permission.WRITE_SECURE_SETTINGS >/dev/null 2>&1 || echo "  (tidak didukung TV ini: sleep timer memakai layar gelap)"
echo "• Notifikasi layanan latar";        sh pm grant "$PKG" android.permission.POST_NOTIFICATIONS >/dev/null 2>&1 || true
echo "• Tidak dibatasi baterai";          sh dumpsys deviceidle whitelist +"$PKG" >/dev/null

echo "• Nonaktifkan launcher bawaan"
for l in "${STOCK_LAUNCHERS[@]}"; do
    installed "$l" && { sh pm disable-user --user 0 "$l" >/dev/null; echo "    nonaktif: $l"; }
done

if [[ "$MODE" != "--biarkan-hoteltv" ]]; then
    for a in "${OTHER_AUTOSTART[@]}"; do
        installed "$a" && { sh pm disable-user --user 0 "$a" >/dev/null; echo "• Nonaktifkan aplikasi auto-start lain: $a"; }
    done
fi

echo
echo "Hasil:"
echo "  HOME           : $(sh cmd package resolve-activity --brief -a android.intent.action.MAIN -c android.intent.category.HOME | tail -1)"
echo "  Overlay        : $(sh appops get "$PKG" SYSTEM_ALERT_WINDOW | cut -d';' -f1)"
echo "  Instal update  : $(sh appops get "$PKG" REQUEST_INSTALL_PACKAGES | cut -d';' -f1)"
echo "  Sleep timer    : $(sh dumpsys package "$PKG" | grep -q 'WRITE_SECURE_SETTINGS: granted=true' && echo diizinkan || echo 'tidak (layar gelap saja)')"
echo
echo "Uji: matikan lalu nyalakan TV, atau:  $ADB -s $TV reboot"
echo "Batalkan semua:  ./scripts/siapkan-tv.sh ${TV%:5555} pulihkan"
