<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\ChannelCategory;
use App\Models\Device;
use App\Services\StreamUrlSigner;
use App\Support\MediaUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChannelController extends Controller
{
    /**
     * Active channels with playable URLs. When provider URLs are signed,
     * `expires_at` tells the TV when to refetch the list.
     */
    public function __invoke(Request $request, StreamUrlSigner $signer): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user();
        $room = $device->room()->with('currentGuest')->first();

        $channels = Channel::query()
            ->visibleTo($room)
            ->with(['category', 'provider'])
            ->withExists(['programmes as has_epg' => fn ($query) => $query->where('ends_at', '>', now())])
            ->orderBy('sort_order')
            ->orderBy('number')
            ->get();

        // Foreign guests: channels in their language come first (numbers stay the same).
        $lang = $room?->currentGuest?->language;
        if ($lang && $lang !== 'id') {
            $channels = $channels->sortBy(fn (Channel $c) => $c->language === $lang ? 0 : 1)->values();
        }

        $expiry = null;
        $items = $channels->map(function (Channel $c, int $i) use ($signer, &$expiry) {
            $signed = $signer->sign($c);
            if ($signed['expires_at'] && ($expiry === null || $signed['expires_at']->lt($expiry))) {
                $expiry = $signed['expires_at'];
            }

            return [
                'id' => $c->id,
                'number' => $c->number,
                'name' => $c->name,
                'category' => $c->category?->name,
                'category_slug' => $c->category?->slug,
                'source_type' => $c->source_type->value,
                'url' => $signed['url'],
                'backup_url' => $signed['backup_url'],
                'drm' => self::drm($c->drm),
                'logo_url' => MediaUrl::of($c->logo_path),
                // Position in the list as the server ordered it (TV sorts by this).
                'sort_order' => $i,
                'is_adult' => $c->is_adult,
                'has_epg' => (bool) $c->has_epg,
            ];
        });

        return response()->json([
            'expires_at' => $expiry?->toIso8601String(),
            'categories' => ChannelCategory::query()->orderBy('sort_order')->get(['id', 'name', 'slug']),
            'channels' => $items,
        ]);
    }

    /** Normalized DRM block: null unless a scheme is set; headers always a JSON object. */
    private static function drm(?array $drm): ?array
    {
        if (empty($drm['scheme']) || empty($drm['license_url'])) {
            return null;
        }

        return [
            'scheme' => $drm['scheme'],
            'license_url' => $drm['license_url'],
            'headers' => (object) array_filter((array) ($drm['headers'] ?? []), fn ($v, $k) => is_string($k) && is_scalar($v), ARRAY_FILTER_USE_BOTH),
        ];
    }
}
