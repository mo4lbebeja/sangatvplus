<?php

use App\Enums\CommandType;
use App\Models\AppRelease;
use App\Models\Channel;
use App\Models\ChannelCheck;
use App\Models\Device;
use App\Models\Guest;
use App\Models\Room;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

it('alerts once when a channel goes down and once when it recovers', function () {
    config(['sangatv.telegram.bot_token' => 'T', 'sangatv.telegram.chat_id' => '1']);
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    $c = Channel::factory()->create(['number' => 101, 'name' => 'TVRI']);
    foreach (range(1, 3) as $i) {
        ChannelCheck::query()->create(['channel_id' => $c->id, 'status' => 'error', 'message' => 'HTTP 503', 'checked_at' => now()->subMinutes(10 * $i)]);
    }

    $this->artisan('sangatv:monitor')->assertSuccessful();
    $this->artisan('sangatv:monitor')->assertSuccessful(); // no duplicate
    Http::assertSentCount(1);
    Http::assertSent(fn ($r) => str_contains($r['text'], 'GAGAL') && str_contains($r['text'], 'TVRI'));

    ChannelCheck::query()->create(['channel_id' => $c->id, 'status' => 'ok', 'checked_at' => now()]);
    $this->artisan('sangatv:monitor')->assertSuccessful();
    Http::assertSentCount(2);
    Http::assertSent(fn ($r) => str_contains($r['text'], 'normal kembali'));
});

it('alerts when a TV that was alive goes silent for too long', function () {
    config(['sangatv.telegram.bot_token' => 'T', 'sangatv.telegram.chat_id' => '1', 'sangatv.alerts.tv_offline_hours' => 24]);
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    $room = Room::factory()->create(['number' => '305']);
    Device::factory()->create(['room_id' => $room->id, 'last_seen_at' => now()->subHours(30)]);
    Device::factory()->create(['last_seen_at' => now()->subDays(20)]); // long dead: ignored

    $this->artisan('sangatv:monitor')->assertSuccessful();
    Http::assertSentCount(1);
    Http::assertSent(fn ($r) => str_contains($r['text'], 'kamar 305'));
});

it('anonymises checked-out guests after the retention period only', function () {
    config(['sangatv.guest_retention_days' => 90]);
    $old = Guest::factory()->create(['name' => 'Budi', 'status' => 'checked_out', 'checked_out_at' => now()->subDays(120), 'external_ref' => 'R1']);
    $recent = Guest::factory()->create(['name' => 'Sari', 'status' => 'checked_out', 'checked_out_at' => now()->subDays(10)]);
    $inHouse = Guest::factory()->create(['name' => 'Ani']);

    $this->artisan('sangatv:privasi-tamu')->expectsOutputToContain('1 data tamu')->assertSuccessful();
    expect($old->fresh()->name)->toBe('Tamu (dianonimkan)')->and($old->fresh()->external_ref)->toBeNull()
        ->and($recent->fresh()->name)->toBe('Sari')->and($inHouse->fresh()->name)->toBe('Ani');
});

it('publishes an uploaded APK and pushes it only to TVs of the same package that are not held', function () {
    Storage::fake('local');
    Storage::disk('local')->put('releases/x.apk', 'APKDATA');
    $sha = hash('sha256', 'APKDATA');
    $rel = Device::factory()->create(['app_package' => 'com.sangatv.plus']);
    $stg = Device::factory()->create(['app_package' => 'com.sangatv.plus.staging']);
    $held = Device::factory()->create(['app_package' => 'com.sangatv.plus', 'hold_updates' => true]);

    $this->artisan('sangatv:rilis', ['file' => 'x.apk', '--paket' => 'com.sangatv.plus', '--code' => 11, '--versi' => '0.3.3', '--sha256' => 'bad'])->assertFailed();
    $this->artisan('sangatv:rilis', ['file' => 'x.apk', '--paket' => 'com.sangatv.plus', '--code' => 11, '--versi' => '0.3.3', '--sha256' => $sha, '--kirim' => true])->assertSuccessful();

    expect(AppRelease::query()->where('package_name', 'com.sangatv.plus')->where('is_active', true)->value('version_name'))->toBe('0.3.3')
        ->and($rel->commands()->where('type', CommandType::CheckUpdate)->count())->toBe(1)
        ->and($stg->commands()->count())->toBe(0)
        ->and($held->commands()->count())->toBe(0);
});
