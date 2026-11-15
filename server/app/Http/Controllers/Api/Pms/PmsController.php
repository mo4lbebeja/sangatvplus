<?php

namespace App\Http\Controllers\Api\Pms;

use App\Enums\GuestStatus;
use App\Http\Controllers\Controller;
use App\Models\Guest;
use App\Models\HotelProfile;
use App\Models\PmsIntegration;
use App\Models\Room;
use App\Services\GuestService;
use App\Services\PmsStayService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Inbound PMS/BMS API (/api/pms/v1). See CMS → Integrasi PMS for the vendor documentation. */
class PmsController extends Controller
{
    public function __construct(private PmsStayService $stays, private GuestService $guests) {}

    private function integration(Request $r): PmsIntegration
    {
        return $r->attributes->get('pms_integration');
    }

    private function event(Request $r, string $name): void
    {
        $r->attributes->set('pms_event', $name);
    }

    private static function ok(array $data, int $status = 200): JsonResponse
    {
        return response()->json(['ok' => true, 'data' => $data], $status);
    }

    private static function fail(string $code, string $message, int $status, array $extra = []): JsonResponse
    {
        return response()->json(['ok' => false, 'error' => ['code' => $code, 'message' => $message, ...$extra]], $status);
    }

    private static function stay(Guest $g): array
    {
        return [
            'reservation_id' => $g->external_ref,
            'room' => $g->room->number,
            'guest' => ['name' => $g->name, 'salutation' => $g->salutation, 'language' => $g->language],
            'status' => $g->status->value,
            'arrival' => $g->checked_in_at?->toIso8601String(),
            'departure' => $g->expected_checkout_at?->toIso8601String(),
            'checked_out_at' => $g->checked_out_at?->toIso8601String(),
        ];
    }

    /** Validation errors in the same envelope as other errors. */
    private function check(Request $r, array $rules): array
    {
        try {
            return $r->validate($rules);
        } catch (ValidationException $e) {
            throw new HttpResponseException(
                self::fail('validation_failed', 'Data tidak valid.', 422, ['fields' => $e->errors()]),
            );
        }
    }

    private static function stayRules(string $prefix = ''): array
    {
        return [
            $prefix.'reservation_id' => ['required', 'string', 'max:64'],
            $prefix.'room' => ['required', 'string', 'max:16'],
            $prefix.'guest' => ['required', 'array'],
            $prefix.'guest.name' => ['required', 'string', 'max:255'],
            $prefix.'guest.salutation' => ['nullable', 'string', 'max:32'],
            $prefix.'guest.language' => ['nullable', 'in:id,en'],
            $prefix.'arrival' => ['nullable', 'date'],
            $prefix.'departure' => ['nullable', 'date'],
        ];
    }

    public function ping(Request $r): JsonResponse
    {
        $this->event($r, 'ping');

        return self::ok(['hotel' => HotelProfile::current()->name, 'integration' => $this->integration($r)->name, 'server_time' => now()->toIso8601String()]);
    }

    public function rooms(Request $r): JsonResponse
    {
        $this->event($r, 'rooms.list');

        return self::ok(['rooms' => Room::query()->orderBy('number')->get()->map(fn (Room $x) => [
            'number' => $x->number, 'floor' => $x->floor, 'type' => $x->type, 'is_active' => $x->is_active,
        ])]);
    }

    public function upsertRoom(Request $r, string $number): JsonResponse
    {
        $this->event($r, 'room.upsert');
        $data = $this->check($r, [
            'floor' => ['nullable', 'integer', 'min:0', 'max:200'],
            'type' => ['nullable', 'string', 'max:64'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        abort_if(strlen($number) > 16, 422);

        $room = Room::query()->firstOrNew(['number' => $number]);
        $room->fill([
            'floor' => $data['floor'] ?? $room->floor ?? PmsStayService::guessFloor($number),
            'type' => array_key_exists('type', $data) ? $data['type'] : $room->type,
            'is_active' => $data['is_active'] ?? $room->is_active ?? true,
        ])->save();

        return self::ok(['room' => ['number' => $room->number, 'floor' => $room->floor, 'type' => $room->type, 'is_active' => $room->is_active], 'created' => $room->wasRecentlyCreated], $room->wasRecentlyCreated ? 201 : 200);
    }

    public function checkIn(Request $r): JsonResponse
    {
        $this->event($r, 'checkin');
        $data = $this->check($r, self::stayRules());
        $room = $this->stays->findRoom($data['room'], $this->integration($r));
        if (! $room) {
            return self::fail('room_not_found', "Kamar {$data['room']} belum terdaftar. Kirim PUT /rooms/{$data['room']} dulu.", 404);
        }

        $res = $this->stays->checkIn($data, $room);

        return self::ok(['action' => $res['action'], 'stay' => self::stay($res['guest']->load('room'))], $res['action'] === 'checked_in' ? 201 : 200);
    }

    public function updateStay(Request $r, string $reservation): JsonResponse
    {
        $this->event($r, 'stay.update');
        $data = $this->check($r, [
            'guest' => ['nullable', 'array'],
            'guest.name' => ['sometimes', 'required', 'string', 'max:255'],
            'guest.salutation' => ['nullable', 'string', 'max:32'],
            'guest.language' => ['nullable', 'in:id,en'],
            'departure' => ['nullable', 'date'],
        ]);
        $guest = $this->stays->inHouse($reservation);
        if (! $guest) {
            return self::fail('stay_not_found', "Reservasi {$reservation} tidak sedang menginap.", 404);
        }

        return self::ok(['stay' => self::stay($this->stays->update($guest, $data)->load('room'))]);
    }

    public function move(Request $r, string $reservation): JsonResponse
    {
        $this->event($r, 'move');
        $data = $this->check($r, ['room' => ['required', 'string', 'max:16']]);
        $guest = $this->stays->inHouse($reservation);
        if (! $guest) {
            return self::fail('stay_not_found', "Reservasi {$reservation} tidak sedang menginap.", 404);
        }
        $room = $this->stays->findRoom($data['room'], $this->integration($r));
        if (! $room) {
            return self::fail('room_not_found', "Kamar {$data['room']} belum terdaftar.", 404);
        }

        return self::ok(['stay' => self::stay($this->guests->moveRoom($guest, $room)->load('room'))]);
    }

    public function checkOut(Request $r): JsonResponse
    {
        $this->event($r, 'checkout');
        $data = $this->check($r, ['reservation_id' => ['required', 'string', 'max:64']]);

        $guest = Guest::query()->with('room')->where('external_ref', $data['reservation_id'])->latest('id')->first();
        if (! $guest) {
            return self::fail('stay_not_found', "Reservasi {$data['reservation_id']} tidak dikenal.", 404);
        }
        $already = $guest->status === GuestStatus::CheckedOut;
        $tvs = $already ? 0 : $this->guests->checkOut($guest);

        return self::ok(['action' => $already ? 'already_checked_out' : 'checked_out', 'tvs_cleared' => $tvs, 'stay' => self::stay($guest->refresh()->load('room'))]);
    }

    public function inHouse(Request $r): JsonResponse
    {
        $this->event($r, 'stays.list');

        return self::ok(['stays' => Guest::query()->with('room')->where('status', GuestStatus::InHouse)->orderBy('room_id')->get()->map(fn (Guest $g) => self::stay($g))]);
    }

    public function sync(Request $r): JsonResponse
    {
        $this->event($r, 'sync');
        $data = $this->check($r, ['stays' => ['present', 'array', 'max:2000'], ...self::stayRules('stays.*.')]);

        return self::ok($this->stays->sync($data['stays'], $this->integration($r)));
    }
}
