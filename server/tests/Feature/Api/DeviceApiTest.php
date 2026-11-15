<?php

use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Enums\ProviderSigning;
use App\Enums\SourceType;
use App\Models\AppRelease;
use App\Models\AppWhitelist;
use App\Models\AuditLog;
use App\Models\Channel;
use App\Models\ChannelProvider;
use App\Models\Device;
use App\Models\Guest;
use App\Models\HotelProfile;
use App\Services\GuestService;

beforeEach(function () {
    $this->device = Device::factory()->create();
    $this->withToken($this->device->createToken('tv', ['tv'])->plainTextToken);
});

it('returns bootstrap data with the in-house guest and no secrets', function () {
    $hotel = HotelProfile::current();
    $hotel->fill(['name' => 'Hotel Uji', 'wifi_ssid' => 'Guest', 'wifi_password' => 'p;ss'])->save();
    Guest::factory()->create(['room_id' => $this->device->room_id, 'salutation' => 'Ibu', 'name' => 'Sari']);

    $res = $this->getJson('/api/v1/bootstrap')->assertOk()
        ->assertJsonPath('hotel.name', 'Hotel Uji')
        ->assertJsonPath('guest.display_name', 'Ibu Sari')
        ->assertJsonPath('hotel.wifi.qr_payload', 'WIFI:T:WPA;S:Guest;P:p\;ss;;');

    expect($res->getContent())->not->toContain('credentials');
});

it('signs provider URLs and never leaks provider credentials', function () {
    $provider = ChannelProvider::create([
        'name' => 'Prov', 'signing' => ProviderSigning::HmacQuery, 'credentials' => ['secret' => 'super-secret-key'], 'token_ttl_seconds' => 600,
    ]);
    Channel::factory()->create(['number' => 201, 'source_type' => SourceType::ProviderHls, 'url' => 'https://cdn.example/live/a.m3u8', 'channel_provider_id' => $provider->id]);
    Channel::factory()->create(['number' => 101, 'url' => 'udp://239.1.1.101:1234']);
    Channel::factory()->create(['number' => 102, 'is_active' => false]);

    $res = $this->getJson('/api/v1/channels')->assertOk()->assertJsonCount(2, 'channels');

    $byNumber = collect($res->json('channels'))->keyBy('number');
    expect($byNumber[101]['url'])->toBe('udp://239.1.1.101:1234')
        ->and($byNumber[201]['url'])->toStartWith('https://cdn.example/live/a.m3u8?expires=')->toContain('&token=')
        ->and($res->json('expires_at'))->not->toBeNull()
        ->and($res->getContent())->not->toContain('super-secret-key');

    // Token verifies with the server-side secret.
    parse_str(parse_url($byNumber[201]['url'], PHP_URL_QUERY), $q);
    $expected = rtrim(strtr(base64_encode(hash_hmac('sha256', '/live/a.m3u8'.$q['expires'], 'super-secret-key', true)), '+/', '-_'), '=');
    expect($q['token'])->toBe($expected);
});

it('delivers checkout commands through the heartbeat and accepts acks', function () {
    AppWhitelist::create(['package_name' => 'com.netflix.ninja', 'label' => 'Netflix', 'clear_on_checkout' => true]);
    AppWhitelist::create(['package_name' => 'com.keep.me', 'label' => 'Keep', 'clear_on_checkout' => false]);
    $guest = Guest::factory()->create(['room_id' => $this->device->room_id]);

    expect(app(GuestService::class)->checkOut($guest))->toBe(1);

    $res = $this->postJson('/api/v1/heartbeat', ['app_version' => '0.2.0', 'kiosk_enabled' => true])->assertOk()
        ->assertJsonCount(1, 'commands')
        ->assertJsonPath('commands.0.type', CommandType::ClearAppData->value)
        ->assertJsonPath('commands.0.payload.packages', ['com.netflix.ninja']);

    expect($this->device->fresh())->kiosk_enabled->toBeTrue()->app_version->toBe('0.2.0')->last_seen_at->not->toBeNull();

    // Delivered once only.
    $this->postJson('/api/v1/heartbeat')->assertJsonCount(0, 'commands');

    $id = $res->json('commands.0.id');
    $this->postJson("/api/v1/commands/{$id}/ack", ['status' => 'done'])->assertOk();
    expect($this->device->commands()->find($id)->status)->toBe(CommandStatus::Done);
});

it('does not let a TV ack another TV command', function () {
    $other = Device::factory()->create();
    $cmd = $other->commands()->create(['type' => CommandType::Refresh, 'status' => CommandStatus::Delivered]);

    $this->postJson("/api/v1/commands/{$cmd->id}/ack", ['status' => 'done'])->assertNotFound();
});

it('changes content_version when content changes', function () {
    $v1 = $this->postJson('/api/v1/heartbeat')->json('content_version');
    $this->travel(2)->seconds();
    Channel::factory()->create();
    $v2 = $this->postJson('/api/v1/heartbeat')->json('content_version');

    expect($v1)->not->toBe($v2);
});

it('locks the technician menu after 5 wrong PINs and logs every attempt', function () {
    $hotel = HotelProfile::current();
    $hotel->setTechnicianPin('482913');
    $hotel->save();

    $this->postJson('/api/v1/technician/verify', ['pin' => '482913'])->assertOk()->assertJsonPath('ok', true);

    foreach (range(1, 5) as $i) {
        $this->postJson('/api/v1/technician/verify', ['pin' => '000000'])->assertStatus(422);
    }
    // Even the correct PIN is refused while locked.
    $this->postJson('/api/v1/technician/verify', ['pin' => '482913'])->assertStatus(423)->assertJsonPath('locked', true);

    $events = AuditLog::where('event', 'like', 'technician.%')->pluck('event')->countBy()->sortKeys();
    expect($events->all())->toBe(['technician.pin_failed' => 5, 'technician.pin_locked' => 1, 'technician.pin_ok' => 1]);
});

it('verifies the PBKDF2 PIN the same way the TV does offline', function () {
    $hotel = HotelProfile::current();
    $hotel->setTechnicianPin('482913');

    $p = $hotel->technician_pin;
    $calc = base64_encode(hash_pbkdf2('sha256', '482913', base64_decode($p['salt']), $p['iterations'], 32, true));

    expect($calc)->toBe($p['hash'])
        ->and($hotel->verifyTechnicianPin('482913'))->toBeTrue()
        ->and($hotel->verifyTechnicianPin('482914'))->toBeFalse();
});

it('offers only newer active releases', function () {
    AppRelease::create(['version_code' => 5, 'version_name' => '1.0.5', 'apk_path' => 'releases/a.apk', 'sha256' => str_repeat('a', 64), 'is_active' => true]);
    AppRelease::create(['version_code' => 6, 'version_name' => '1.0.6', 'apk_path' => 'releases/b.apk', 'sha256' => str_repeat('b', 64), 'is_active' => false]);

    $this->getJson('/api/v1/app/update?version_code=4')->assertJsonPath('update.version_code', 5);
    $this->getJson('/api/v1/app/update?version_code=5')->assertJsonPath('update', null);
});

it('normalizes DRM so empty headers are a JSON object and incomplete DRM is null', function () {
    Channel::factory()->create(['number' => 301, 'source_type' => SourceType::ProviderDash, 'url' => 'https://x/a.mpd',
        'drm' => ['scheme' => 'widevine', 'license_url' => 'https://lic/x', 'headers' => []]]);
    Channel::factory()->create(['number' => 302, 'source_type' => SourceType::ProviderDash, 'url' => 'https://x/b.mpd',
        'drm' => ['scheme' => null, 'license_url' => null, 'headers' => []]]);

    $res = $this->getJson('/api/v1/channels')->assertOk();
    expect($res->getContent())->toContain('"headers":{}');
    $byNumber = collect($res->json('channels'))->keyBy('number');
    expect($byNumber[302]['drm'])->toBeNull()->and($byNumber[301]['drm']['scheme'])->toBe('widevine');
});

it('records the auto-start status reported by the TV', function () {
    $this->postJson('/api/v1/heartbeat', [
        'app_version' => '0.1.0', 'is_default_home' => true, 'stock_launcher_active' => true, 'can_draw_overlays' => true,
    ])->assertOk();
    expect($this->device->fresh()->autoStartState())->toBe('partial');

    $this->postJson('/api/v1/heartbeat', ['is_default_home' => true, 'stock_launcher_active' => false])->assertOk();
    expect($this->device->fresh()->autoStartState())->toBe('ok');
});

it('logs whitelisted technician actions and rejects unknown ones', function () {
    $this->postJson('/api/v1/technician/events', ['event' => 'launcher_restore_opened', 'detail' => 'com.google.android.tvlauncher'])->assertOk();
    $this->postJson('/api/v1/technician/events', ['event' => 'rm_rf'])->assertStatus(422);

    $this->assertDatabaseHas('audit_logs', ['event' => 'technician.launcher_restore_opened', 'actor_id' => $this->device->id]);
});

it('sends the screensaver delay chosen in the CMS to the TV', function () {
    HotelProfile::current()->fill(['screensaver_minutes' => 60])->save();

    $this->getJson('/api/v1/bootstrap')->assertOk()->assertJsonPath('config.screensaver_minutes', 60);
});

it('tracks screen on / standby / off for monitoring', function () {
    $this->postJson('/api/v1/heartbeat', ['screen_on' => true])->assertOk();
    expect($this->device->fresh()->powerState())->toBe('on');

    $this->postJson('/api/v1/heartbeat', ['screen_on' => false])->assertOk();
    $d = $this->device->fresh();
    expect($d->powerState())->toBe('standby')->and($d->screen_changed_at)->not->toBeNull();

    $d->forceFill(['last_seen_at' => now()->subMinutes(10)])->save();
    expect($d->fresh()->powerState())->toBe('off');
});
