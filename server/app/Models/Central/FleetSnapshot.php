<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Jumlah TV online per hotel tiap 15 menit (dasar uptime/SLA). */
class FleetSnapshot extends Model
{
    protected $connection = 'central';

    protected $guarded = ['id'];

    public const UPDATED_AT = null;

    public const CREATED_AT = null;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['taken_at' => 'datetime'];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }
}
