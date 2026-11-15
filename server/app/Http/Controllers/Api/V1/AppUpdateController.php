<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AppRelease;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AppUpdateController extends Controller
{
    /**
     * Latest active release newer than the TV's version. The TV must verify
     * sha256 AND that the APK is signed with the same certificate before installing.
     */
    public function check(Request $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user();
        $current = (int) $request->integer('version_code');
        // Releases only go to the same app package (staging never receives a release build and vice versa).
        $package = (string) ($request->query('package') ?: $device->app_package ?: AppRelease::DEFAULT_PACKAGE);

        if ($device->hold_updates) {
            return response()->json(['update' => null, 'held' => true]);
        }

        $release = AppRelease::query()
            ->where('package_name', $package)
            ->where('is_active', true)
            ->where('version_code', '>', $current)
            ->orderByDesc('version_code')
            ->first();

        return response()->json(['update' => $release ? [
            'version_code' => $release->version_code,
            'version_name' => $release->version_name,
            'sha256' => $release->sha256,
            'notes' => $release->notes,
            'download_url' => route('api.v1.app.download', $release),
        ] : null]);
    }

    public function download(AppRelease $release): StreamedResponse
    {
        abort_unless($release->is_active && Storage::disk('local')->exists($release->apk_path), 404);

        return Storage::disk('local')->download($release->apk_path, 'sangatv-plus-'.$release->version_name.'.apk', [
            'Content-Type' => 'application/vnd.android.package-archive',
        ]);
    }
}
