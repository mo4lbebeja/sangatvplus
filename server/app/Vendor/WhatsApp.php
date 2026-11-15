<?php

namespace App\Vendor;

use App\Models\Central\VendorSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/** WhatsApp messages through Fonnte, Wablas or a custom HTTP endpoint (Console → Pengaturan → Integrasi). */
class WhatsApp
{
    public static function enabled(): bool
    {
        return VendorSetting::read('whatsapp_provider') !== 'none' && filled(VendorSetting::secret('whatsapp_token'));
    }

    /** 0812-xxx / +62812 / 62812 → 62812xxx */
    public static function normalize(?string $phone): ?string
    {
        $d = preg_replace('/\D+/', '', (string) $phone);
        if ($d === '' || strlen($d) < 9) {
            return null;
        }

        return str_starts_with($d, '0') ? '62'.substr($d, 1) : (str_starts_with($d, '62') ? $d : '62'.$d);
    }

    public static function send(?string $phone, string $message): bool
    {
        $to = self::normalize($phone);
        if (! $to || ! self::enabled()) {
            return false;
        }
        $token = (string) VendorSetting::secret('whatsapp_token');
        $url = rtrim((string) VendorSetting::read('whatsapp_url'), '/');
        try {
            $res = match (VendorSetting::read('whatsapp_provider')) {
                'fonnte' => Http::withHeaders(['Authorization' => $token])->asForm()->timeout(20)
                    ->post('https://api.fonnte.com/send', ['target' => $to, 'message' => $message, 'countryCode' => '62']),
                'wablas' => Http::withHeaders(['Authorization' => $token])->asForm()->timeout(20)
                    ->post(($url ?: 'https://solo.wablas.com').'/api/send-message', ['phone' => $to, 'message' => $message]),
                default => Http::withToken($token)->acceptJson()->timeout(20)->post($url, ['phone' => $to, 'message' => $message]),
            };

            return $res->successful();
        } catch (Throwable $e) {
            Log::warning('WhatsApp gagal: '.$e->getMessage());

            return false;
        }
    }
}
