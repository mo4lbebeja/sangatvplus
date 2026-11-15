# Integrasi PMS → SangaTV Plus

**Untuk:** tim pengembang vendor PMS/BMS · **Hotel:** {{ $hotel }} · **Versi API:** v1

SangaTV Plus adalah sistem TV kamar hotel. PMS mengirim data **kamar** dan **tamu menginap** ke API ini
setiap ada perubahan (check-in, ubah data, pindah kamar, check-out). TV kamar menampilkan nama tamu
dan menghapus data aplikasi tamu (YouTube, Netflix, dll.) saat check-out.

| | |
|---|---|
| Base URL | `{{ $base }}` |
| Format | JSON (UTF-8), `Content-Type: application/json` |
| Waktu | ISO 8601, contoh `2026-11-15T14:00:00+07:00` |
| Kunci API | Diberikan oleh hotel: **Key ID** (`pms_…`) dan **Secret** |

## 1. Autentikasi

Setiap request wajib membawa header berikut.

| Header | Isi |
|---|---|
| `X-SangaTV-Key` | Key ID |
| `X-SangaTV-Timestamp` | Waktu UNIX dalam detik. Selisih dengan server maksimal 5 menit |
| `X-SangaTV-Signature` | `hex( HMAC-SHA256( secret, "{timestamp}.{METHOD}.{path}.{body}" ) )` |
| `Idempotency-Key` | Disarankan untuk POST/PUT/PATCH: ID unik per kejadian di PMS |

- `METHOD`: huruf besar, mis. `POST`.
- `path`: path tanpa domain dan query, mis. `/api/pms/v1/checkins`.
- `body`: isi request **persis** seperti yang dikirim (string JSON mentah). Untuk GET: string kosong.

Contoh (bash):

```bash
KEY="pms_xxxxxxxxxxxxxxxxxxxx"; SECRET="…"
BODY='{"reservation_id":"R-1001","room":"101","guest":{"name":"Budi Santoso","salutation":"MR"}}'
TS=$(date +%s)
SIG=$(printf '%s' "$TS.POST./api/pms/v1/checkins.$BODY" | openssl dgst -sha256 -hmac "$SECRET" | sed 's/^.* //')
curl -X POST "{{ $base }}/checkins" \
  -H "Content-Type: application/json" -H "X-SangaTV-Key: $KEY" \
  -H "X-SangaTV-Timestamp: $TS" -H "X-SangaTV-Signature: $SIG" \
  -H "Idempotency-Key: evt-$(date +%s%N)" -d "$BODY"
```

Contoh (PHP):

```php
$ts  = (string) time();
$sig = hash_hmac('sha256', "$ts.POST./api/pms/v1/checkins.$body", $secret);
```

> Bila PMS tidak bisa membuat tanda tangan HMAC, hotel dapat mengaktifkan mode **Bearer**:
> kirim `Authorization: Bearer <secret>` + `X-SangaTV-Key`. Mode ini hanya dipakai bersama pembatasan IP.

## 2. Endpoint

| Kejadian | Method & path |
|---|---|
| Cek koneksi | `GET /ping` |
| Daftar kamar | `GET /rooms` |
| Tambah/ubah kamar | `PUT /rooms/{nomor}` |
| **Check-in** | `POST /checkins` |
| Ubah data tamu | `PATCH /stays/{reservation_id}` |
| **Pindah kamar** | `POST /stays/{reservation_id}/move` |
| **Check-out** | `POST /checkouts` |
| Tamu menginap saat ini | `GET /stays` |
| **Sinkron penuh** | `POST /sync` |

### Check-in — `POST /checkins`

```json
{
  "reservation_id": "R-1001",
  "room": "101",
  "guest": { "name": "Budi Santoso", "salutation": "MR", "language": "id" },
  "arrival": "2026-11-15T14:00:00+07:00",
  "departure": "2026-11-17T12:00:00+07:00"
}
```

| Field | Wajib | Keterangan |
|---|---|---|
| `reservation_id` | ya | ID unik reservasi/folio di PMS (maks 64) |
| `room` | ya | Nomor kamar, sama persis dengan di hotel (maks 16) |
| `guest.name` | ya | Nama yang tampil di TV |
| `guest.salutation` | tidak | `MR`, `MRS`, `MS`, `BAPAK`/`BPK`, `IBU`, … |
| `guest.language` | tidak | `id` (bawaan) atau `en`; bahasa tampilan TV |
| `arrival`, `departure` | tidak | Waktu tiba & rencana keluar |

Respons `201` (`checked_in`) atau `200` (`updated` / `moved`, bila reservasi sudah menginap):

```json
{ "ok": true, "data": { "action": "checked_in", "stay": { "reservation_id": "R-1001", "room": "101", "status": "in_house", "guest": { "name": "Budi Santoso", "salutation": "Mr.", "language": "id" } } } }
```

Mengirim ulang check-in yang sama **aman**: data diperbarui, tidak dibuat ganda. Bila kamarnya berbeda, tamu dipindahkan.

### Ubah data — `PATCH /stays/{reservation_id}`

```json
{ "guest": { "name": "Budi S.", "salutation": "MR" }, "departure": "2026-11-18T12:00:00+07:00" }
```

### Pindah kamar — `POST /stays/{reservation_id}/move`

```json
{ "room": "205" }
```

### Check-out — `POST /checkouts`

```json
{ "reservation_id": "R-1001" }
```

Aman diulang: respons kedua `already_checked_out`.

### Kamar — `PUT /rooms/{nomor}`

```json
{ "floor": 2, "type": "Deluxe", "is_active": true }
```

### Sinkron penuh — `POST /sync`

Kirim **semua** tamu yang sedang menginap, misalnya saat interface pertama kali dipasang, atau
satu kali sehari sebagai pengaman. Tamu dari PMS yang tidak ada di daftar akan di-check-out.
Tamu yang diinput manual di CMS hotel tidak disentuh.

```json
{ "stays": [ { "reservation_id": "R-1001", "room": "101", "guest": { "name": "Budi Santoso" } } ] }
```

Respons: `{ "ok": true, "data": { "checked_in": 1, "updated": 0, "moved": 0, "checked_out": 0, "errors": [] } }`

## 3. Error

Semua error memakai format yang sama:

```json
{ "ok": false, "error": { "code": "room_not_found", "message": "Kamar 305 belum terdaftar." } }
```

| HTTP | `code` | Arti / tindakan |
|---|---|---|
| 401 | `unauthorized` | Key ID salah atau dinonaktifkan hotel |
| 401 | `invalid_signature` | Tanda tangan tidak cocok (cek secret, path, body mentah) |
| 401 | `stale_timestamp` | Jam server PMS selisih > 5 menit, sinkronkan NTP |
| 401 | `invalid_token` | Mode Bearer: token salah |
| 403 | `forbidden_ip` | IP pengirim tidak diizinkan |
| 404 | `room_not_found` | Kirim `PUT /rooms/{nomor}` dulu |
| 404 | `stay_not_found` | Reservasi tidak sedang menginap |
| 422 | `validation_failed` | Lihat `error.fields` |
| 429 | – | Terlalu banyak request (batas 600/menit). Coba lagi |
| 5xx | – | Gangguan server. **Kirim ulang** dengan `Idempotency-Key` yang sama |

## 4. Rekomendasi

1. Kirim kejadian **segera** setelah terjadi di PMS (check-in, pindah, check-out).
2. Saat gagal (timeout/5xx), **ulangi** dengan `Idempotency-Key` yang sama (mis. 3× dengan jeda bertambah).
3. Jalankan **sinkron penuh** saat pemasangan awal dan 1× sehari.
4. Pastikan **jam server PMS** sinkron (NTP).
5. Uji dulu dengan `GET /ping`.
