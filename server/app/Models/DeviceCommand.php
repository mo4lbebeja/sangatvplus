<?php

namespace App\Models;

use App\Enums\CommandStatus;
use App\Enums\CommandType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceCommand extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => CommandType::class,
            'status' => CommandStatus::class,
            'payload' => 'array',
            'result' => 'array',
            'delivered_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
