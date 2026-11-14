<?php

use App\Models\Device;
use App\Models\ProvisioningCode;
use App\Models\Room;
use App\Models\User;

function provision(string $code, string $uid = 'uid-1')
{
    return test()->postJson('/api/v1/devices/provision', [
        'code' => $code, 'device_uid' => $uid, 'model' => 'Sharp', 'app_version' => '0.1.0', 'device_owner' => true,
    ]);
}

it('exchanges a valid one-time code for a device token bound to the room', function () {
    $room = Room::factory()->create(['number' => '214']);
    [, $plain] = ProvisioningCode::issue($room);

    $res = provision($plain)->assertCreated()->assertJsonPath('device.room', '214');

    $device = Device::firstWhere('device_uid', 'uid-1');
    expect($device->room_id)->toBe($room->id)
        ->and($device->tokens()->count())->toBe(1)
        ->and($res->json('token'))->toBeString();

    $this->withToken($res->json('token'))->getJson('/api/v1/bootstrap')
        ->assertOk()->assertJsonPath('room.number', '214');
});

it('accepts codes regardless of case or dash', function () {
    [, $plain] = ProvisioningCode::issue(Room::factory()->create());
    provision(strtolower(str_replace('-', '', $plain)))->assertCreated();
});

it('rejects unknown, used and expired codes', function () {
    $room = Room::factory()->create();
    provision('AAAA-BBBB')->assertStatus(422);

    [, $plain] = ProvisioningCode::issue($room);
    provision($plain, 'a')->assertCreated();
    provision($plain, 'b')->assertStatus(422);

    [$code, $plain2] = ProvisioningCode::issue($room);
    $code->update(['expires_at' => now()->subMinute()]);
    provision($plain2, 'c')->assertStatus(422);

    $this->assertDatabaseHas('audit_logs', ['event' => 'device.provision_failed']);
});

it('revokes the old token when the same TV is provisioned again', function () {
    $room = Room::factory()->create();
    [, $c1] = ProvisioningCode::issue($room);
    $old = provision($c1)->json('token');
    [, $c2] = ProvisioningCode::issue($room);
    provision($c2)->assertCreated();

    app('auth')->forgetGuards();
    $this->withToken($old)->getJson('/api/v1/bootstrap')->assertUnauthorized();
});

it('blocks revoked devices and non-device tokens', function () {
    $device = Device::factory()->create();
    $token = $device->createToken('tv', ['tv'])->plainTextToken;
    $this->withToken($token)->getJson('/api/v1/bootstrap')->assertOk();

    $device->revoke();
    app('auth')->forgetGuards();
    $this->withToken($token)->getJson('/api/v1/bootstrap')->assertUnauthorized();

    // A device token without the "tv" ability is refused.
    $other = Device::factory()->create();
    $limited = $other->createToken('x', ['other'])->plainTextToken;
    app('auth')->forgetGuards();
    $this->withToken($limited)->getJson('/api/v1/bootstrap')->assertForbidden();

    // CMS users cannot mint API tokens at all.
    expect(method_exists(User::class, 'createToken'))->toBeFalse();
});

it('requires authentication for device endpoints', function () {
    $this->getJson('/api/v1/channels')->assertUnauthorized();
    $this->postJson('/api/v1/heartbeat')->assertUnauthorized();
});
