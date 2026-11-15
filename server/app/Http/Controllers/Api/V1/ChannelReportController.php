<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\ChannelReport;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** TVs report playback problems so the CMS shows broken channels before guests complain. */
class ChannelReportController extends Controller
{
    public function store(Request $request, Channel $channel): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user();
        $data = $request->validate([
            'status' => ['required', 'in:failover,failed,recovered'],
            'message' => ['nullable', 'string', 'max:255'],
        ]);

        // At most one identical report per TV/channel/status every 5 minutes.
        $dupe = ChannelReport::query()->where('channel_id', $channel->id)->where('device_id', $device->id)
            ->where('status', $data['status'])->where('created_at', '>', now()->subMinutes(5))->exists();
        if (! $dupe) {
            ChannelReport::create(['channel_id' => $channel->id, 'device_id' => $device->id, 'status' => $data['status'], 'message' => $data['message'] ?? null]);
        }

        return response()->json(['ok' => true, 'recorded' => ! $dupe]);
    }
}
