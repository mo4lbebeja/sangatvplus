<?php

use App\Enums\SourceType;
use App\Models\Channel;
use App\Services\ChannelHealthChecker;
use Illuminate\Support\Facades\Http;

it('marks HLS channels ok/error and skips multicast', function () {
    Http::fake([
        'good.example/*' => Http::response("#EXTM3U\n#EXT-X-VERSION:3\n", 200),
        'html.example/*' => Http::response('<html>blocked</html>', 200),
        'down.example/*' => Http::response('', 503),
        'backup.example/*' => Http::response("#EXTM3U\n", 200),
    ]);
    $ok = Channel::factory()->create(['number' => 501, 'source_type' => SourceType::LocalHls, 'url' => 'https://good.example/a.m3u8']);
    $html = Channel::factory()->create(['number' => 502, 'source_type' => SourceType::LocalHls, 'url' => 'https://html.example/a.m3u8']);
    $down = Channel::factory()->create(['number' => 503, 'source_type' => SourceType::LocalHls, 'url' => 'https://down.example/a.m3u8']);
    $fallback = Channel::factory()->create(['number' => 504, 'source_type' => SourceType::LocalHls, 'url' => 'https://down.example/b.m3u8', 'backup_url' => 'https://backup.example/b.m3u8']);
    $udp = Channel::factory()->create(['number' => 505, 'url' => 'udp://239.1.1.5:1234']);

    $n = app(ChannelHealthChecker::class)->checkAll();

    expect($n)->toBe(['ok' => 2, 'error' => 2, 'skipped' => 1])
        ->and($ok->fresh()->health_status)->toBe('ok')
        ->and($html->fresh()->health_message)->toBe('Bukan playlist/manifest yang valid')
        ->and($down->fresh()->health_message)->toBe('HTTP 503')
        ->and($fallback->fresh()->health_message)->toContain('cadangan normal')
        ->and($udp->fresh()->health_status)->toBe('skipped');

    $this->artisan('sangatv:check-channels')->assertSuccessful();
});
