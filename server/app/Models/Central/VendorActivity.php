<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/** What Sijalucom staff (or the system) did: activations, payments, suspensions, support logins. */
class VendorActivity extends Model
{
    protected $connection = 'central';

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['properties' => 'array', 'created_at' => 'datetime'];
    }

    public static function record(string $action, string $description, ?Hotel $hotel = null, array $properties = []): self
    {
        $user = Auth::guard('vendor')->user();

        return self::query()->create([
            'vendor_user_id' => $user instanceof VendorUser ? $user->id : null,
            'hotel_id' => $hotel?->id,
            'action' => $action,
            'description' => $description,
            'properties' => $properties ?: null,
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(VendorUser::class, 'vendor_user_id');
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }
}
