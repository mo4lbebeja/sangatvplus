# SangaTV Plus — Ringkasan Proyek

**SangaTV Plus** adalah aplikasi TV hotel (IPTV + launcher) untuk Android TV / Google TV.
Dokumen ini adalah acuan untuk Claude Code. Baca seluruhnya sebelum menulis kode.

## 1. Tujuan

Aplikasi launcher + IPTV untuk TV di kamar hotel (Android TV / Google TV).
Tamu menonton channel TV, melihat info hotel, dan membuka aplikasi yang diizinkan.
Pengelola hotel mengatur semuanya dari panel admin (CMS) berbasis web.

Prinsip: **interaktif, modern, mudah dipakai dengan remote, ringan, dan keamanan paling diutamakan.**

## 2. Konteks lapangan

- Hotel 96 kamar, 4 lantai.
- Jaringan Ruijie; setiap kamar punya port khusus untuk TV (terpisah dari IP phone).
- TV target: Sharp (Google TV) dan TCL (Android TV). TV konsumen, RAM kecil (±1,5–2 GB).
- Rencana PMS hotel: VHP (integrasi menyusul, bukan bagian MVP).

## 3. Arsitektur

```
Sumber channel
 ├─ Server lokal: receiver DVB-T2/satelit → encoder → UDP multicast / HLS (VLAN IPTV)
 └─ Penyedia channel (internet): HLS/DASH, bisa pakai DRM (Widevine) + token
          │
Server Laravel (API + CMS)  ── menyimpan kredensial penyedia, membuat URL/token berumur pendek
          │  HTTPS (CA internal + certificate pinning)
          ▼
Aplikasi TV (Kotlin) ── launcher hotel, kiosk mode, Media3 player
```

Aplikasi TV **tidak tahu dan tidak peduli** asal channel. Ia hanya menerima daftar channel dari API, tiap channel punya `source_type`.

## 4. Teknologi

| Bagian | Teknologi |
|---|---|
| Aplikasi TV | Kotlin, Jetpack Compose for TV, Media3 (ExoPlayer), Retrofit/OkHttp, Kotlin Coroutines/Flow |
| Server & CMS | Laravel (PHP), MySQL/PostgreSQL, Sanctum (token), panel admin |
| Streaming lokal | Encoder IPTV → multicast UDP (IGMP snooping aktif di switch) atau HLS |

Alasan Kotlin native: paling ringan, navigasi D-pad matang, ExoPlayer resmi (HLS, UDP, Widevine), dan akses penuh ke Device Owner / Lock Task / Keystore.

## 5. Model data channel (kontrak API)

```json
{
  "number": 101,
  "name": "TVRI Nasional",
  "category": "Nasional",
  "source_type": "local_multicast",
  "url": "udp://239.1.1.101:1234",
  "backup_url": "https://provider.example/tvri.m3u8",
  "drm": null,
  "logo_url": null,
  "sort_order": 10,
  "is_active": true
}
```

- `source_type`: `local_multicast` | `local_hls` | `provider_hls` | `provider_dash`
- Untuk sumber penyedia: `url` adalah URL bertanda tangan / token berumur pendek (5–10 menit) yang dibuat server. **Kredensial penyedia tidak pernah dikirim ke TV.**
- `backup_url`: failover otomatis jika sumber utama gagal.
- Kategori awal: Semua, Nasional, Berita, Olahraga, Anak, Hotel (channel internal).

Entitas lain di server: `rooms`, `devices` (TV terdaftar, token per kamar), `guests` (nama tamu untuk sambutan), `slides` (latar beranda), `facilities`, `app_whitelist`, `audit_logs`.

## 6. Keamanan (wajib sejak awal)

1. **Jaringan**: VLAN IPTV terpisah dari Wi-Fi tamu, IP phone, dan kantor. Isolasi antar-port (TV antar kamar tidak saling akses). Firewall: TV hanya boleh ke API server dan sumber siaran. Panel admin tidak bisa diakses dari VLAN TV.
2. **Perangkat TV**: aplikasi sebagai Device Owner + launcher default + Lock Task (kiosk). ADB/developer mode dimatikan setelah provisioning. Hanya aplikasi whitelist yang bisa dibuka.
3. **Komunikasi**: HTTPS dengan CA internal + certificate pinning. Setiap TV punya token unik terikat ke nomor kamar; token bisa dicabut dari CMS.
4. **Aplikasi**: APK ditandatangani, R8/obfuscation aktif, tidak ada secret di dalam APK. Update OTA dari server sendiri wajib diverifikasi tanda tangannya sebelum dipasang.
5. **Privasi tamu**: saat check-out, hapus data aplikasi pihak ketiga (login YouTube/Netflix, dll.) lewat Device Owner. Awalnya dipicu manual dari CMS, nanti otomatis dari PMS VHP.
6. **Menu teknisi**: dibuka dengan kombinasi tombol tersembunyi + PIN 6 digit. Salah 5× → terkunci 15 menit. Semua percobaan dicatat di log server.
7. **Server**: role-based access (Admin, Front Office, IT), audit log, rate limit API.

## 7. Desain UI

Mockup: https://claude.ai/artifact/9ck4KEmMZKJaoj63KHrvWp (4 layar, 1920×1080).

### Palet "biru pantai"

| Peran | Warna |
|---|---|
| Latar paling gelap (area video) | `#031220` |
| Latar utama | `#061A2B` |
| Panel samping | `#08223A` |
| Kartu / surface | `#0C2B45` |
| Surface 2 / tombol | `#123756` |
| Garis / border | `#1A4366`, `#2C5A80` |
| Aksen (fokus, aktif) | `#38C6D9` (toska) |
| Teks di atas aksen | `#04232D` |
| Biru tua (tab aktif di bar putih) | `#0B3D66` |
| Bar menu bawah (terang) | `#F2F8FB`, teks `#0B2C47` |
| Teks utama | `#F2F8FB` |
| Teks sekunder | `#CFE0EC`, `#9DB9CF` |
| Error | `#F08A7E` |

Font: **Michroma** (judul / nama hotel, huruf kapital, letter-spacing lebar) + **Plus Jakarta Sans** (teks lain).

### Layar

1. **Landing / Beranda (launcher)** — layar pertama yang muncul saat TV dinyalakan
   - Alur nyala TV: TV boot → SangaTV Plus otomatis terbuka (terdaftar sebagai HOME launcher default + Device Owner) → langsung tampil layar ini. Tidak ada launcher bawaan TV yang sempat terlihat.
   - Tombol HOME di remote selalu kembali ke layar ini.
   - Bila TV tidak terhubung ke server, tetap tampilkan landing dari cache lokal (slide, nama hotel, info terakhir) + indikator offline kecil.
   - Slide latar penuh layar (foto hotel/promo dari CMS), ganti otomatis tiap ±6 detik dengan fade; indikator slide di kiri bawah.
   - Kiri atas: nama/logo hotel. Kiri tengah: teks slide (kicker + judul kapital + garis toska + deskripsi).
   - Kanan atas: jam + tanggal, garis pemisah, cuaca.
   - Bar putih bawah (tinggi ±150 px): logo kiri; menu Beranda, TV, Info Hotel, Room Service, Fasilitas, Aplikasi, panah "lainnya"; menu aktif = latar biru tua miring + garis toska; kanan: suhu, jam, label "Kamar 214", "Selamat datang, Bapak/Ibu [Nama Tamu]".
2. **Daftar channel (overlay saat menonton)**
   - Video full screen; panel kiri transparan (±760 px) berisi judul, filter kategori (pill), daftar channel (nomor, nama, acara/EPG, label sumber Lokal/Penyedia). Channel terpilih berlatar toska.
   - Info bar bawah: nomor besar, nama channel, label sumber, acara, progress bar.
   - Petunjuk remote: ▲▼ pilih, ◀▶ kategori, OK tonton, BACK tutup. Tombol angka langsung pindah channel; BACK saat menonton = kembali ke Beranda (BACK saat daftar terbuka = tutup daftar); tombol LAST/RECALL di remote = channel sebelumnya.
3. **Info hotel & fasilitas**: menu kiri (Tentang Hotel, Fasilitas, Restoran & Room Service, Wi-Fi, Kontak Resepsionis, Info Check-out), kartu fasilitas (foto, nama, lantai, jam), kartu QR Wi-Fi, kontak resepsionis.
4. **Menu teknisi**: keypad PIN, titik PIN, status perangkat (ID, kamar, koneksi server, mode kiosk, versi).

Catatan: layar 2–4 masih bergaya lama; samakan dengan gaya Beranda (Michroma untuk judul, aksen toska, sudut miring) saat implementasi.

### Prinsip UI untuk TV

- Semua elemen bisa dijangkau D-pad; fokus selalu terlihat jelas (skala ±1.06 + warna aksen).
- Area aman (overscan): padding minimal ±48 px dari tepi.
- Animasi singkat (150–300 ms), tanpa video latar, gambar 1080p terkompresi.
- Bahasa: Indonesia (siapkan struktur untuk Inggris).

## 8. Tahapan

1. **Survei**: versi Android & RAM TV Sharp/TCL; apakah launcher bisa diganti di Google TV Sharp (kemungkinan via ADB); format sumber channel lokal & penyedia (HLS? DRM? API/EPG?).
2. **MVP aplikasi TV**: proyek Kotlin + Compose for TV; ambil daftar channel (JSON) dari API; putar HLS dan UDP multicast dengan Media3; navigasi D-pad; uji di 1 TV. Untuk uji awal, server bisa menyajikan file video sebagai HLS.
3. **CMS Laravel**: CRUD channel (nomor, kategori, sumber, backup), slide beranda, fasilitas, info hotel, nama tamu per kamar, registrasi perangkat.
4. **Launcher & UI lengkap**: Beranda dengan slide, info hotel, overlay channel, sesuai desain.
5. **Pengamanan**: VLAN/firewall, Device Owner + kiosk, pinning, token per TV, menu teknisi, hapus data saat check-out, OTA bertanda tangan.
6. **Pilot**: 1 lantai → perbaikan → seluruh 96 kamar.
7. **Lanjutan**: integrasi PMS VHP (nama tamu & check-out otomatis), room service order, EPG, casting dari HP tamu.

## 9. Pertanyaan terbuka

- Penyedia channel mana, formatnya apa (HLS/DASH, DRM, API daftar channel/EPG)?
- Perangkat encoder untuk channel lokal?
- Lisensi siaran ulang channel di hotel (cek ke pemilik konten/penyedia).
- Sumber data cuaca (API apa, lewat server).
- Foto hotel asli untuk slide dan kartu fasilitas.

## 10. Struktur repo yang disarankan

```
sangatv-plus/
├─ CLAUDE.md            ← dokumen ini
├─ tv-app/              ← proyek Android (Kotlin, Compose for TV)
└─ server/              ← Laravel (API + CMS)
```

Penamaan:
- Nama tampilan aplikasi: **SangaTV Plus**
- Package Android (usulan): `com.sangatv.plus`
- Nama panel admin: **SangaTV Plus CMS**
- Nama hotel tetap diambil dari CMS (layar tamu menampilkan nama hotel, bukan nama produk)
