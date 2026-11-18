<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AppWhitelist;
use App\Models\Channel;
use App\Models\Device;
use App\Models\HotelProfile;
use App\Models\Slide;
use App\Services\WeatherService;
use App\Support\MediaUrl;
use App\Tenancy\HotelManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Everything the launcher needs to render (and cache for offline use). */
class BootstrapController extends Controller
{
    public function __invoke(Request $request, WeatherService $weather): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user();
        $room = $device->room()->with('currentGuest')->first();
        $guest = $room->currentGuest;
        $hotel = HotelProfile::current();

        return response()->json([
            'server_time' => now()->toIso8601String(),
            // Multi-hotel: TVs registered before multi-hotel learn their hotel code here (then send X-SangaTV-Hotel).
            'hotel_code' => app(HotelManager::class)->current()?->slug,
            'hotel' => [
                'name' => $hotel->name,
                'tagline' => $hotel->tagline,
                'about' => $hotel->about,
                'logo_url' => MediaUrl::of($hotel->logo_path),
                'address' => $hotel->address,
                'reception_phone' => $hotel->reception_phone,
                'reception_extension' => $hotel->reception_extension,
                'wifi' => $hotel->wifi_ssid ? [
                    'ssid' => $hotel->wifi_ssid,
                    'password' => $hotel->wifi_password,
                    // Standard Wi-Fi QR payload; TV renders the QR itself.
                    'qr_payload' => sprintf('WIFI:T:WPA;S:%s;P:%s;;', self::escapeWifi($hotel->wifi_ssid), self::escapeWifi((string) $hotel->wifi_password)),
                ] : null,
                'check_in_time' => $hotel->check_in_time,
                'check_out_time' => $hotel->check_out_time,
                'check_out_info' => $hotel->check_out_info,
                'timezone' => $hotel->timezone,
            ],
            'room' => ['number' => $room->number, 'floor' => $room->floor],
            'guest' => $guest ? [
                'display_name' => $guest->displayName(),
                'name' => $guest->name,
                'salutation' => $guest->salutation,
                'language' => $guest->language,
            ] : null,
            'slides' => Slide::query()->visible()->orderBy('sort_order')->get()->map(fn (Slide $s) => [
                'id' => $s->id,
                'kicker' => $s->kicker,
                'title' => $s->title,
                'description' => $s->description,
                'image_url' => MediaUrl::of($s->image_path),
            ]),
            'apps_background_url' => Slide::appsBackgroundUrl(),
            'apps' => AppWhitelist::query()->where('is_active', true)->orderBy('sort_order')->get()->map(fn (AppWhitelist $a) => [
                'package_name' => $a->package_name,
                'label' => $a->label,
                'icon_url' => MediaUrl::of($a->icon_path),
            ]),
            'home_music' => $hotel->home_music_enabled && $hotel->home_music_path ? [
                'url' => MediaUrl::of($hotel->home_music_path),
                'volume' => $hotel->home_music_volume,
            ] : null,
            'service' => $hotel->servicePayload(),
            'technician_pin' => $hotel->technician_pin,
            'adult_pin' => $hotel->adult_pin,
            'favorites' => array_values(array_map('intval', (array) ($guest?->favorite_channel_ids ?? []))),
            'announcements' => Announcement::payloadFor($room),
            'weather' => $weather->current($hotel, $guest?->language ?? 'id'),
            'config' => [
                'heartbeat_seconds' => 60,
                'slide_interval_seconds' => 6,
                'screensaver_minutes' => $hotel->screensaver_minutes,
                'start_volume' => $hotel->tv_start_volume,
                'start_channel_number' => $hotel->tv_start_channel_id ? Channel::query()->whereKey($hotel->tv_start_channel_id)->where('is_active', true)->value('number') : null,
                'show_hdmi_inputs' => $hotel->show_hdmi_inputs,
                'theme' => $hotel->tv_theme ?: 'ocean',
            ],
        ]);
    }

    private static function escapeWifi(string $v): string
    {
        return preg_replace('/([\\\;,:"])/', '\\\\$1', $v);
    }
}
