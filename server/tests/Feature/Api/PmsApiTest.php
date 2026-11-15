<?php

use App\Enums\CommandType;
use App\Enums\GuestStatus;
use App\Models\Device;
use App\Models\Guest;
use App\Models\PmsEvent;
use App\Models\PmsIntegration;
use App\Models\Room;

beforeEach(function () {
    $this->pms = PmsIntegration::create([
        'name' => 'VHP', 'key_id' => PmsIntegration::newKeyId(), 'secret' => 'rahasia-uji-1234567890', 'auth_mode' => 'hmac',
    ]);
    Room::factory()->create(['number' => '101', 'floor' => 1]);
    Room::factory()->create(['number' => '102', 'floor' => 1]);
});

/** Signed request the way the PMS vendor must do it. */
function pms(string $method, string $path, array $body = [], array $headers = [], ?int $ts = null)
{
    $t = test();
    $raw = $method === 'GET' ? '' : json_encode($body);
    $ts ??= time();
    $sig = $t->pms->signatureFor((string) $ts, $method, $path, $raw);

    return $t->call($method, $path, [], [], [], array_merge([
        'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_SANGATV_KEY' => $t->pms->key_id, 'HTTP_X_SANGATV_TIMESTAMP' => (string) $ts, 'HTTP_X_SANGATV_SIGNATURE' => $sig,
    ], $headers), $raw);
}

function checkinBody(string $res = 'R-1001', string $room = '101', string $name = 'Budi Santoso'): array
{
    return ['reservation_id' => $res, 'room' => $room, 'guest' => ['name' => $name, 'salutation' => 'MR', 'language' => 'id'],
        'arrival' => '2026-11-15T14:00:00+07:00', 'departure' => '2026-11-17T12:00:00+07:00'];
}

it('rejects unknown keys, bad signatures, stale timestamps and blocked IPs', function () {
    $this->call('GET', '/api/pms/v1/ping', server: ['HTTP_ACCEPT' => 'application/json'])->assertStatus(401)->assertJsonPath('error.code', 'unauthorized');
    pms('GET', '/api/pms/v1/ping', headers: ['HTTP_X_SANGATV_SIGNATURE' => 'salah'])->assertStatus(401)->assertJsonPath('error.code', 'invalid_signature');
    pms('GET', '/api/pms/v1/ping', ts: time() - 900)->assertStatus(401)->assertJsonPath('error.code', 'stale_timestamp');

    $this->pms->update(['allowed_ips' => ['10.9.9.9']]);
    pms('GET', '/api/pms/v1/ping')->assertStatus(403)->assertJsonPath('error.code', 'forbidden_ip');

    $this->pms->update(['allowed_ips' => [], 'is_active' => false]);
    pms('GET', '/api/pms/v1/ping')->assertStatus(401);

    expect(PmsEvent::where('status', 'error')->count())->toBe(5);
});

it('detects a tampered body', function () {
    $raw = json_encode(checkinBody());
    $ts = (string) time();
    $sig = $this->pms->signatureFor($ts, 'POST', '/api/pms/v1/checkins', $raw);
    $tampered = str_replace('Budi', 'Mallory', $raw);

    $this->call('POST', '/api/pms/v1/checkins', [], [], [], [
        'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_SANGATV_KEY' => $this->pms->key_id, 'HTTP_X_SANGATV_TIMESTAMP' => $ts, 'HTTP_X_SANGATV_SIGNATURE' => $sig,
    ], $tampered)->assertStatus(401);
    expect(Guest::count())->toBe(0);
});

it('supports bearer mode for PMS that cannot sign', function () {
    $this->pms->update(['auth_mode' => 'bearer']);
    $this->getJson('/api/pms/v1/ping', ['X-SangaTV-Key' => $this->pms->key_id, 'Authorization' => 'Bearer rahasia-uji-1234567890'])->assertOk()->assertJsonPath('ok', true);
    $this->getJson('/api/pms/v1/ping', ['X-SangaTV-Key' => $this->pms->key_id, 'Authorization' => 'Bearer salah'])->assertStatus(401);
});

it('checks a guest in and the room TV shows the name', function () {
    pms('POST', '/api/pms/v1/checkins', checkinBody())->assertCreated()
        ->assertJsonPath('data.action', 'checked_in')
        ->assertJsonPath('data.stay.guest.salutation', 'Mr.')
        ->assertJsonPath('data.stay.room', '101');

    $guest = Guest::firstWhere('external_ref', 'R-1001');
    expect($guest->source)->toBe('pms')->and($guest->expected_checkout_at)->not->toBeNull();

    $device = Device::factory()->create(['room_id' => Room::firstWhere('number', '101')->id]);
    app('auth')->forgetGuards();
    $this->withToken($device->createToken('tv', ['tv'])->plainTextToken)->getJson('/api/v1/bootstrap')
        ->assertJsonPath('guest.display_name', 'Mr. Budi Santoso');
});

it('is idempotent: repeated check-in updates instead of duplicating', function () {
    pms('POST', '/api/pms/v1/checkins', checkinBody())->assertCreated();
    pms('POST', '/api/pms/v1/checkins', checkinBody(name: 'Budi S.'))->assertOk()->assertJsonPath('data.action', 'updated');

    expect(Guest::where('external_ref', 'R-1001')->count())->toBe(1)
        ->and(Guest::firstWhere('external_ref', 'R-1001')->name)->toBe('Budi S.');
});

it('replays the stored response for a repeated Idempotency-Key', function () {
    pms('POST', '/api/pms/v1/checkins', checkinBody(), ['HTTP_IDEMPOTENCY_KEY' => 'evt-77'])->assertCreated();
    pms('POST', '/api/pms/v1/checkins', checkinBody(name: 'Lain'), ['HTTP_IDEMPOTENCY_KEY' => 'evt-77'])
        ->assertCreated()->assertHeader('Idempotent-Replayed', 'true');

    expect(Guest::firstWhere('external_ref', 'R-1001')->name)->toBe('Budi Santoso');
});

it('rejects unknown rooms unless auto-create is enabled', function () {
    pms('POST', '/api/pms/v1/checkins', checkinBody(room: '305'))->assertNotFound()->assertJsonPath('error.code', 'room_not_found');

    $this->pms->update(['auto_create_rooms' => true]);
    pms('POST', '/api/pms/v1/checkins', checkinBody(room: '305'))->assertCreated();
    expect(Room::firstWhere('number', '305')->floor)->toBe(3);
});

it('moves a guest and wipes guest apps on the old room TV', function () {
    $oldTv = Device::factory()->create(['room_id' => Room::firstWhere('number', '101')->id]);
    pms('POST', '/api/pms/v1/checkins', checkinBody())->assertCreated();

    pms('POST', '/api/pms/v1/stays/R-1001/move', ['room' => '102'])->assertOk()->assertJsonPath('data.stay.room', '102');

    expect(Guest::firstWhere('external_ref', 'R-1001')->room->number)->toBe('102')
        ->and($oldTv->commands()->first()->type)->toBe(CommandType::ClearAppData)
        ->and($oldTv->commands()->first()->payload['reason'])->toBe('room_move');
});

it('checks out and is safe to repeat', function () {
    $tv = Device::factory()->create(['room_id' => Room::firstWhere('number', '101')->id]);
    pms('POST', '/api/pms/v1/checkins', checkinBody())->assertCreated();

    pms('POST', '/api/pms/v1/checkouts', ['reservation_id' => 'R-1001'])->assertOk()
        ->assertJsonPath('data.action', 'checked_out')->assertJsonPath('data.tvs_cleared', 1);
    pms('POST', '/api/pms/v1/checkouts', ['reservation_id' => 'R-1001'])->assertOk()->assertJsonPath('data.action', 'already_checked_out');

    expect(Guest::firstWhere('external_ref', 'R-1001')->status)->toBe(GuestStatus::CheckedOut)
        ->and($tv->commands()->count())->toBe(1);
});

it('updates guest details', function () {
    pms('POST', '/api/pms/v1/checkins', checkinBody())->assertCreated();
    pms('PATCH', '/api/pms/v1/stays/R-1001', ['guest' => ['salutation' => 'Ibu', 'name' => 'Sari'], 'departure' => '2026-11-20'])->assertOk()
        ->assertJsonPath('data.stay.guest.name', 'Sari')->assertJsonPath('data.stay.guest.salutation', 'Ibu');
});

it('full sync checks in listed stays and checks out missing PMS guests only', function () {
    pms('POST', '/api/pms/v1/checkins', checkinBody('R-OLD', '102', 'Tamu Lama'))->assertCreated();
    $manual = Guest::factory()->create(['room_id' => Room::factory()->create(['number' => '201', 'floor' => 2])->id, 'source' => 'cms']);

    pms('POST', '/api/pms/v1/sync', ['stays' => [checkinBody('R-1001', '101'), checkinBody('R-404', '999')]])->assertOk()
        ->assertJsonPath('data.checked_in', 1)
        ->assertJsonPath('data.checked_out', 1)
        ->assertJsonPath('data.errors.0.code', 'room_not_found');

    expect(Guest::firstWhere('external_ref', 'R-OLD')->status)->toBe(GuestStatus::CheckedOut)
        ->and($manual->fresh()->status)->toBe(GuestStatus::InHouse);
});

it('upserts rooms and lists in-house stays', function () {
    pms('PUT', '/api/pms/v1/rooms/410', ['floor' => 4, 'type' => 'Deluxe'])->assertCreated()->assertJsonPath('data.created', true);
    pms('PUT', '/api/pms/v1/rooms/410', ['type' => 'Suite'])->assertOk()->assertJsonPath('data.room.type', 'Suite');
    pms('POST', '/api/pms/v1/checkins', checkinBody())->assertCreated();
    pms('GET', '/api/pms/v1/stays')->assertOk()->assertJsonCount(1, 'data.stays');
});

it('returns validation errors in the standard envelope and logs them', function () {
    pms('POST', '/api/pms/v1/checkins', ['room' => '101'])->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_failed')
        ->assertJsonStructure(['error' => ['fields' => ['reservation_id', 'guest']]]);

    $this->assertDatabaseHas('pms_events', ['event' => 'checkin', 'status' => 'error', 'http_status' => 422]);
});
