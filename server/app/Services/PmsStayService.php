<?php

namespace App\Services;

use App\Enums\GuestStatus;
use App\Models\Guest;
use App\Models\PmsIntegration;
use App\Models\Room;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Applies PMS stay events. A "stay" is identified by the PMS reservation id
 * (stored in guests.external_ref). All operations are idempotent: replaying the
 * same event leaves the data unchanged.
 */
class PmsStayService
{
    public function __construct(private GuestService $guests) {}

    public function findRoom(string $number, PmsIntegration $integration): ?Room
    {
        $number = trim($number);
        $room = Room::query()->where('number', $number)->first();
        if ($room || ! $integration->auto_create_rooms) {
            return $room;
        }

        return Room::create(['number' => $number, 'floor' => self::guessFloor($number), 'type' => null, 'is_active' => true]);
    }

    public static function guessFloor(string $number): int
    {
        $digits = preg_replace('/\D/', '', $number);

        return strlen($digits) >= 3 ? (int) substr($digits, 0, strlen($digits) - 2) : 0;
    }

    public function inHouse(string $reservationId): ?Guest
    {
        return Guest::query()->where('external_ref', $reservationId)->where('status', GuestStatus::InHouse)->latest('id')->first();
    }

    /**
     * @param  array{reservation_id: string, guest: array{name: string, salutation?: ?string, language?: ?string}, arrival?: ?string, departure?: ?string}  $data
     * @return array{guest: Guest, action: string}
     */
    public function checkIn(array $data, Room $room): array
    {
        return DB::transaction(function () use ($data, $room) {
            $attrs = $this->guestAttributes($data);
            $existing = $this->inHouse($data['reservation_id']);

            if ($existing) {
                $existing->update($attrs);
                if ($existing->room_id !== $room->id) {
                    return ['guest' => $this->guests->moveRoom($existing, $room), 'action' => 'moved'];
                }

                return ['guest' => $existing->refresh(), 'action' => 'updated'];
            }

            $guest = $this->guests->checkIn($room, [
                ...$attrs,
                'external_ref' => $data['reservation_id'],
                'source' => 'pms',
                'checked_in_at' => isset($data['arrival']) ? CarbonImmutable::parse($data['arrival']) : now(),
            ]);

            return ['guest' => $guest, 'action' => 'checked_in'];
        });
    }

    public function update(Guest $guest, array $data): Guest
    {
        $guest->update($this->guestAttributes($data, partial: true));

        return $guest->refresh();
    }

    /**
     * Full reconciliation: every listed stay is checked in/updated; PMS guests that are
     * in-house here but missing from the list are checked out. CMS-entered guests are untouched.
     *
     * @param  list<array>  $stays
     * @return array{checked_in: int, updated: int, moved: int, checked_out: int, errors: list<array>}
     */
    public function sync(array $stays, PmsIntegration $integration): array
    {
        $r = ['checked_in' => 0, 'updated' => 0, 'moved' => 0, 'checked_out' => 0, 'errors' => []];
        $seen = [];

        foreach ($stays as $i => $stay) {
            $room = $this->findRoom((string) $stay['room'], $integration);
            if (! $room) {
                $r['errors'][] = ['index' => $i, 'reservation_id' => $stay['reservation_id'], 'code' => 'room_not_found', 'room' => $stay['room']];

                continue;
            }
            $res = $this->checkIn($stay, $room);
            $r[$res['action']]++;
            $seen[] = $stay['reservation_id'];
        }

        Guest::query()->where('status', GuestStatus::InHouse)->where('source', 'pms')
            ->whereNotNull('external_ref')->whereNotIn('external_ref', $seen)->get()
            ->each(function (Guest $g) use (&$r) {
                $this->guests->checkOut($g);
                $r['checked_out']++;
            });

        return $r;
    }

    private function guestAttributes(array $data, bool $partial = false): array
    {
        $g = $data['guest'] ?? [];
        $attrs = [];
        if (! $partial || array_key_exists('name', $g)) {
            $attrs['name'] = trim((string) ($g['name'] ?? ''));
        }
        if (! $partial || array_key_exists('salutation', $g)) {
            $attrs['salutation'] = self::salutation($g['salutation'] ?? null);
        }
        if (! $partial || array_key_exists('language', $g)) {
            $attrs['language'] = in_array($g['language'] ?? null, ['id', 'en'], true) ? $g['language'] : 'id';
        }
        if (array_key_exists('departure', $data)) {
            $attrs['expected_checkout_at'] = $data['departure'] ? CarbonImmutable::parse($data['departure']) : null;
        }

        return $attrs;
    }

    /** Normalises common PMS titles (MR, MRS, BPK, IBU …) to what the TV shows. */
    public static function salutation(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return match (strtolower(rtrim(trim($value), '.'))) {
            'mr', 'tuan', 'tn' => 'Mr.',
            'mrs', 'nyonya', 'ny' => 'Mrs.',
            'ms', 'miss', 'nona', 'nn' => 'Ms.',
            'bapak', 'bpk', 'pak', 'bp' => 'Bapak',
            'ibu', 'bu', 'ib' => 'Ibu',
            default => mb_substr(trim($value), 0, 32),
        };
    }
}
