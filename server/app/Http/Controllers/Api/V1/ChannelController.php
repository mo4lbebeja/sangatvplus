<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\ChannelCategory;
use App\Services\StreamUrlSigner;
use App\Support\MediaUrl;
use Illuminate\Http\JsonResponse;

class ChannelController extends Controller
{
    /**
     * Active channels with playable URLs. When provider URLs are signed,
     * `expires_at` tells the TV when to refetch the list.
     */
    public function __invoke(StreamUrlSigner $signer): JsonResponse
    {
        $channels = Channel::query()
            ->with(['category', 'provider'])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('number')
            ->get();

        $expiry = null;
        $items = $channels->map(function (Channel $c) use ($signer, &$expiry) {
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
                'sort_order' => $c->sort_order,
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
