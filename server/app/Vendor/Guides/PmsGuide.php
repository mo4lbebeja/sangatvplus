<?php

namespace App\Vendor\Guides;

/** PMS / BMS integration: questions for the hotel's PMS vendor and the API document handed to them. */
class PmsGuide
{
    /** @return array<string, array{q: string, hint: string}> */
    public static function questions(): array
    {
        return [
            'rest' => ['q' => 'Apakah PMS bisa mengirim HTTP/REST (webhook) ke sistem luar?', 'hint' => 'Paling penting. Bila hanya FIAS (protokol TV/PBX hotel lewat TCP), perlu penghubung (middleware) di server hotel.'],
            'events' => ['q' => 'Kejadian apa saja yang bisa dikirim?', 'hint' => 'Minimal: check-in, check-out, pindah kamar, ubah nama tamu.'],
            'format' => ['q' => 'Format nomor kamar dan sapaan yang dipakai PMS?', 'hint' => 'Mis. 101 / 0101 / A-101; MR, MRS, BPK, IBU. Nomor kamar harus sama dengan di CMS.'],
            'retry' => ['q' => 'Apakah PMS mengirim ulang saat gagal (retry) dan bisa sinkron penuh?', 'hint' => 'Sinkron penuh = daftar semua tamu menginap; dipakai saat pemasangan awal & 1× sehari.'],
            'source' => ['q' => 'Dari mana kiriman berasal (server PMS di hotel atau cloud) dan IP-nya?', 'hint' => 'IP dipakai untuk pembatasan di kunci API.'],
            'auth' => ['q' => 'Apakah PMS bisa membuat tanda tangan HMAC-SHA256?', 'hint' => 'Bila tidak, gunakan mode Bearer + pembatasan IP.'],
            'clock' => ['q' => 'Apakah jam server PMS sinkron (NTP)?', 'hint' => 'Selisih waktu > 5 menit membuat kiriman ditolak (stale_timestamp).'],
            'cost' => ['q' => 'Biaya/lisensi modul interface, kontak teknis, dan jadwal pemasangan?', 'hint' => 'Biasanya modul interface PMS berbayar.'],
            'sandbox' => ['q' => 'Apakah vendor punya lingkungan uji (sandbox) sebelum live?', 'hint' => 'Bisa diuji ke production dengan kunci API terpisah dan kamar uji.'],
        ];
    }

    public static function baseUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/api/pms/v1';
    }

    public static function vendorMarkdown(string $hotelName): string
    {
        return view('pms.vendor-doc', ['base' => self::baseUrl(), 'hotel' => $hotelName])->render();
    }
}
