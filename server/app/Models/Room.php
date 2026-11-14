<?php

namespace App\Models;

use App\Enums\GuestStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Room extends Model
{
    use Auditable, HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'floor' => 'integer'];
    }

    public function guests(): HasMany
    {
        return $this->hasMany(Guest::class);
    }

    public function currentGuest(): HasOne
    {
        return $this->hasOne(Guest::class)->ofMany(
            ['checked_in_at' => 'max', 'id' => 'max'],
            fn ($q) => $q->where('status', GuestStatus::InHouse),
        );
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }
}
