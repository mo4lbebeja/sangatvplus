<?php

namespace App\Services;

use App\Models\HotelProfile;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Weather for the TV Home screen, fetched and cached by the server (TVs never call third parties).
 * Primary source: BMKG open data (free, official; attribution "BMKG" is required and always shown).
 * Open-Meteo is only a fallback when WEATHER_ENABLED=true (its free tier is non-commercial).
 */
class WeatherService
{
    /** @return array{temperature: float, code: int, description: string}|null */
    public function current(HotelProfile $hotel, string $lang = 'id'): ?array
    {
        if (filled($hotel->bmkg_adm4)) {
            return $this->bmkg((string) $hotel->bmkg_adm4, $lang);
        }

        return $this->openMeteo($hotel);
    }

    /** @return array{desa: string, kecamatan: string, kotkab: string}|null location of a BMKG code (to confirm it in the CMS) */
    public function bmkgLocation(string $adm4): ?array
    {
        $data = $this->bmkgForecast($adm4);

        return $data ? ['desa' => (string) data_get($data, 'lokasi.desa'), 'kecamatan' => (string) data_get($data, 'lokasi.kecamatan'), 'kotkab' => (string) data_get($data, 'lokasi.kotkab')] : null;
    }

    private function bmkg(string $adm4, string $lang): ?array
    {
        $data = $this->bmkgForecast($adm4);
        $slots = collect(data_get($data, 'data.0.cuaca', []))->flatten(1)->filter(fn ($s) => isset($s['utc_datetime'], $s['t']));
        if ($slots->isEmpty()) {
            return null;
        }
        // Latest 3-hour slot that has started, otherwise the first upcoming one.
        $now = CarbonImmutable::now('UTC');
        $slot = $slots->filter(fn ($s) => CarbonImmutable::parse($s['utc_datetime'], 'UTC')->lte($now))->last() ?? $slots->first();
        $desc = $lang === 'en' ? ($slot['weather_desc_en'] ?? $slot['weather_desc'] ?? '') : ($slot['weather_desc'] ?? '');

        return [
            'temperature' => round((float) $slot['t'], 1),
            'code' => (int) ($slot['weather'] ?? 0),
            'description' => trim($desc.' · BMKG', ' ·'),
        ];
    }

    private function bmkgForecast(string $adm4): ?array
    {
        return Cache::remember('weather:bmkg:'.$adm4, now()->addMinutes(30), function () use ($adm4) {
            try {
                $res = Http::timeout(6)->withHeaders(['User-Agent' => 'SangaTVPlus'])->get('https://api.bmkg.go.id/publik/prakiraan-cuaca', ['adm4' => $adm4]);

                return $res->ok() && data_get($res->json(), 'lokasi.adm4') ? $res->json() : null;
            } catch (Throwable) {
                return null;
            }
        });
    }

    private function openMeteo(HotelProfile $hotel): ?array
    {
        if ($hotel->latitude === null || $hotel->longitude === null || ! config('services.weather.enabled')) {
            return null;
        }
        $key = sprintf('weather:%.3f:%.3f', $hotel->latitude, $hotel->longitude);

        return Cache::remember($key, now()->addMinutes(30), function () use ($hotel) {
            try {
                $res = Http::timeout(4)->get('https://api.open-meteo.com/v1/forecast', [
                    'latitude' => $hotel->latitude, 'longitude' => $hotel->longitude,
                    'current' => 'temperature_2m,weather_code', 'timezone' => $hotel->timezone,
                ]);
                if (! $res->ok()) {
                    return null;
                }
                $code = (int) $res->json('current.weather_code');

                return ['temperature' => round((float) $res->json('current.temperature_2m'), 1), 'code' => $code, 'description' => self::describe($code)];
            } catch (Throwable) {
                return null;
            }
        });
    }

    public static function describe(int $code): string
    {
        return match (true) {
            $code === 0 => 'Cerah',
            $code <= 2 => 'Cerah berawan',
            $code === 3 => 'Berawan',
            $code <= 48 => 'Berkabut',
            $code <= 57 => 'Gerimis',
            $code <= 67 => 'Hujan',
            $code <= 77 => 'Salju',
            $code <= 82 => 'Hujan lebat',
            default => 'Badai petir',
        };
    }
}
