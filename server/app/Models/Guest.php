<?php

namespace App\Models;

use App\Enums\GuestStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Guest extends Model
{
    use Auditable, HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => GuestStatus::class,
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'expected_checkout_at' => 'datetime',
            'favorite_channel_ids' => 'array',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function displayName(): string
    {
        return trim(($this->salutation ? $this->salutation.' ' : '').$this->name);
    }
}
