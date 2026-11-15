<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\Device;
use App\Models\EpgProgramme;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EpgController extends Controller
{
    /** Programme guide for the channels this room can watch: 1 h back to `hours` ahead (max 48). */
    public function __invoke(Request $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user();
        $hours = min(48, max(3, (int) $request->query('hours', 24)));
        $ids = Channel::query()->visibleTo($device->room)->pluck('id');

        $programmes = EpgProgramme::query()
            ->whereIn('channel_id', $ids)
            ->where('ends_at', '>', now()->subHour())
            ->where('starts_at', '<', now()->addHours($hours))
            ->orderBy('starts_at')
            ->get(['channel_id', 'starts_at', 'ends_at', 'title', 'description', 'category'])
            ->groupBy('channel_id')
            ->map(fn ($list) => $list->map(fn (EpgProgramme $p) => [
                'start' => $p->starts_at->toIso8601String(),
                'end' => $p->ends_at->toIso8601String(),
                'title' => $p->title,
                'description' => $p->description ? mb_substr($p->description, 0, 400) : null,
                'category' => $p->category,
            ])->values());

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'programmes' => (object) $programmes->all(),
        ]);
    }
}
