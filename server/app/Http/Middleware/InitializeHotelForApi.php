<?php

namespace App\Http\Middleware;

use App\Models\Central\Hotel;
use App\Models\Central\HotelCode;
use App\Models\Central\PmsKeyIndex;
use App\Models\ProvisioningCode;
use App\Tenancy\HotelFeatures;
use App\Tenancy\HotelManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API (before authentication): pick the hotel database for this request.
 *  - TV registration: the typed code → central hotel_codes index.
 *  - TV calls: header X-SangaTV-Hotel (sent after registration); missing → the legacy (first) hotel.
 *  - PMS calls: header X-SangaTV-Key → central pms_key_index; unknown → the legacy hotel.
 * A token from hotel A sent with hotel B's header is simply not found in B's database (401).
 */
class InitializeHotelForApi
{
    public const HEADER = 'X-SangaTV-Hotel';

    public function __construct(private HotelManager $tenancy) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Payment gateway webhooks only touch the central database.
        if (! $this->tenancy->enabled() || $request->is('api/payments/*')) {
            return $next($request);
        }

        $hotel = match (true) {
            $request->is('api/v1/devices/provision') => $this->byProvisioningCode((string) $request->input('code')),
            $request->is('api/pms/*') => $this->byPmsKey((string) $request->header('X-SangaTV-Key')),
            default => $request->hasHeader(self::HEADER) ? $this->tenancy->findBySlug($request->header(self::HEADER)) : $this->tenancy->legacy(),
        };

        if (! $hotel) {
            return response()->json(['message' => 'Hotel tidak dikenal.'], $request->is('api/v1/devices/provision') ? 422 : 401);
        }
        if (! $hotel->isAccessible()) {
            return response()->json(['message' => 'Layanan hotel ini sedang ditangguhkan.'], 403);
        }

        $this->tenancy->initialize($hotel);
        if ($request->is('api/pms/*') && ! HotelFeatures::has('pms')) {
            return response()->json(['message' => 'Paket SangaTV Plus hotel ini tidak termasuk integrasi PMS.'], 403);
        }

        return $next($request);
    }

    private function byProvisioningCode(string $code): ?Hotel
    {
        $index = HotelCode::query()->with('hotel')->where('code_hash', ProvisioningCode::hashCode($code))->first();

        return $index?->hotel ?? $this->tenancy->legacy();
    }

    private function byPmsKey(string $keyId): ?Hotel
    {
        $hotelId = $keyId !== '' ? PmsKeyIndex::query()->whereKey($keyId)->value('hotel_id') : null;

        return $hotelId ? Hotel::query()->find($hotelId) : $this->tenancy->legacy();
    }
}
