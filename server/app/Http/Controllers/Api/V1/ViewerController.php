<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\ChannelView;
use App\Models\Device;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Guest-side channel data: favourites (kept on the stay) and anonymous viewing sessions. */
class ViewerController extends Controller
{
    public function favorites(Request $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user();
        $data = $request->validate([
            'channel_ids' => ['present', 'array', 'max:60'],
            'channel_ids.*' => ['integer'],
        ]);

        $guest = $device->room()->first()?->currentGuest()->first();
        if (! $guest) {
            return response()->json(['ok' => false, 'reason' => 'no_guest'], 409);
        }

        $ids = Channel::query()->whereKey($data['channel_ids'])->pluck('id')->all();
        // Ordered as the guest added them; quiet save: a favourite is not an audited change.
        $ordered = array_values(array_filter(array_unique(array_map('intval', $data['channel_ids'])), fn ($id) => in_array($id, $ids, true)));
        $guest->forceFill(['favorite_channel_ids' => $ordered])->saveQuietly();

        return response()->json(['ok' => true, 'favorites' => $ordered]);
    }

    public function views(Request $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user();
        $data = $request->validate([
            'views' => ['required', 'array', 'max:100'],
            'views.*.channel_id' => ['required', 'integer'],
            'views.*.started_at' => ['required', 'date'],
            'views.*.seconds' => ['required', 'integer', 'min:0'],
        ]);

        $valid = Channel::query()->whereKey(array_column($data['views'], 'channel_id'))->pluck('id')->flip();
        $rows = [];
        foreach ($data['views'] as $v) {
            $start = CarbonImmutable::parse($v['started_at'])->utc();
            // Ignore zapping (< 10 s), cap runaway sessions at 6 h, drop clock-skewed entries.
            if (! $valid->has($v['channel_id']) || $v['seconds'] < 10 || $start->gt(now()->addMinutes(5)) || $start->lt(now()->subDays(7))) {
                continue;
            }
            $rows[] = [
                'channel_id' => (int) $v['channel_id'],
                'room_id' => $device->room_id,
                'started_at' => $start->format('Y-m-d H:i:s'),
                'seconds' => min(6 * 3600, (int) $v['seconds']),
            ];
        }
        if ($rows) {
            ChannelView::query()->insert($rows);
        }

        return response()->json(['ok' => true, 'stored' => count($rows)]);
    }
}
