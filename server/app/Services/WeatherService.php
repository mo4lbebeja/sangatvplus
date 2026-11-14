<?php

namespace App\Services;

use App\Models\HotelProfile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Current weather via Open-Meteo (no API key). Fetched by the server and
 * cached; TVs never call third-party services directly.
 */
class WeatherService
{
    /** @return array{temperature: float, code: int, description: string}|null */
    public function current(HotelProfile $hotel): ?array
    {
        if ($hotel->latitude === null || $hotel->longitude === null || ! config('services.weather.enabled')) {
            return null;
        }

        $key = sprintf('weather:%.3f:%.3f', $hotel->latitude, $hotel->longitude);

        return Cache::remember($key, now()->addMinutes(30), function () use ($hotel) {
            try {
                $res = Http::timeout(4)->get('https://api.open-meteo.com/v1/forecast', [
                    'latitude' => $hotel->latitude,
                    'longitude' => $hotel->longitude,
                    'current' => 'temperature_2m,weather_code',
                    'timezone' => $hotel->timezone,
                ]);
                if (! $res->ok()) {
                    return null;
                }
                $code = (int) $res->json('current.weather_code');

                return [
                    'temperature' => round((float) $res->json('current.temperature_2m'), 1),
                    'code' => $code,
                    'description' => self::describe($code),
                ];
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
