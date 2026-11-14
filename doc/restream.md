# Restream channel internet ke jaringan hotel

**Masalahnya:** bila tiap TV menarik stream sendiri dari internet, satu channel HD (3–5 Mbps)
yang ditonton banyak kamar bisa memakan 300–500 Mbps.
**Solusinya:** satu server di hotel menarik tiap channel **sekali saja**, lalu membagikannya
ke kamar lewat VLAN IPTV. Cara ini juga sesuai firewall di dokumen utama (§6): TV cukup
diizinkan mengakses server hotel.

```
Internet (penyedia) ──1×──▶ Server restream (ffmpeg) ──▶ VLAN IPTV ──▶ 96 TV
```

Yang dibutuhkan: server Linux kecil di VLAN IPTV dengan `ffmpeg` dan `nginx`.
Opsi `-c copy` hanya menyalin stream tanpa encode ulang, jadi beban CPU-nya ringan.

## Opsi A — HLS lokal (paling sederhana)

```bash
mkdir -p /var/www/iptv/ch201
ffmpeg -hide_banner -loglevel warning \
  -reconnect 1 -reconnect_streamed 1 -reconnect_delay_max 5 \
  -i "https://penyedia.example/live/ch201.m3u8" \
  -c copy -f hls -hls_time 4 -hls_list_size 6 \
  -hls_flags delete_segments+omit_endlist \
  /var/www/iptv/ch201/index.m3u8
```

Sajikan `/var/www/iptv` lewat nginx. Di CMS, isi channel dengan:
- **Jenis sumber:** Lokal – HLS
- **URL:** `http://<ip-server>/iptv/ch201/index.m3u8`
- **URL cadangan** (opsional): URL asli dari penyedia, kalau firewall mengizinkan.

## Opsi B — UDP multicast (paling hemat untuk banyak TV)

```bash
ffmpeg -hide_banner -loglevel warning \
  -reconnect 1 -reconnect_streamed 1 -reconnect_delay_max 5 \
  -i "https://penyedia.example/live/ch201.m3u8" \
  -c copy -f mpegts "udp://239.1.1.201:1234?pkt_size=1316&ttl=4"
```

Di CMS, isi channel dengan:
- **Jenis sumber:** Lokal – UDP multicast
- **URL:** `udp://239.1.1.201:1234`

Syaratnya, IGMP snooping di switch Ruijie harus aktif. Tanpa itu, multicast membanjiri semua port.

## Jalankan otomatis (systemd, satu file untuk semua channel)

`/etc/systemd/system/restream@.service`:

```ini
[Unit]
Description=Restream channel %i
After=network-online.target

[Service]
EnvironmentFile=/etc/restream/%i.env
ExecStart=/usr/bin/ffmpeg -hide_banner -loglevel warning -reconnect 1 -reconnect_streamed 1 -reconnect_delay_max 5 -i ${SOURCE} -c copy -f mpegts ${TARGET}
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

`/etc/restream/ch201.env`:

```
SOURCE=https://penyedia.example/live/ch201.m3u8
TARGET=udp://239.1.1.201:1234?pkt_size=1316&ttl=4
```

Lalu aktifkan:

```bash
systemctl enable --now restream@ch201
```

## Batasan

- **Channel ber-DRM (Widevine)** tidak bisa di-restream karena isinya terenkripsi. Untuk channel
  seperti ini, gunakan solusi hotel dari penyedia, atau biarkan TV memutar langsung dengan
  jenis sumber *Penyedia* + DRM.
- **URL bertoken dari penyedia** harus diperbarui secara berkala di file `.env`
  (tanyakan masa berlaku token ke penyedia).
- **Lisensi:** restream ke kamar tetap butuh izin dari pemilik konten atau penyedia (dokumen utama §9).
- **Tvheadend** adalah alternatif berbasis web bila juga memakai tuner DVB-T2 atau satelit.
