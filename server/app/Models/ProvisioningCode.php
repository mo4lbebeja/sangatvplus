<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ProvisioningCode extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime'];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public static function hashCode(string $code): string
    {
        return hash('sha256', strtoupper(str_replace([' ', '-'], '', $code)));
    }

    /**
     * Issue a one-time code for a room. Returns [model, plainCode];
     * the plain code is shown once in the CMS and never stored.
     *
     * @return array{0: self, 1: string}
     */
    public static function issue(Room $room, ?User $by = null, int $ttlMinutes = 30): array
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no 0/O/1/I
        $plain = collect(range(1, 8))->map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)])->implode('');

        $model = static::create([
            'room_id' => $room->id,
            'code_hash' => static::hashCode($plain),
            'expires_at' => now()->addMinutes($ttlMinutes),
            'created_by' => $by?->id,
        ]);

        return [$model, Str::substr($plain, 0, 4).'-'.Str::substr($plain, 4)];
    }

    public function isUsable(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }
}
