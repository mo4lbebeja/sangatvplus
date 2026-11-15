<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One viewing session (≥ 10 s) on a room TV. Stored per room, never per guest. */
class ChannelView extends Model
{
    use Prunable;

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'seconds' => 'integer'];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function prunable(): Builder
    {
        return static::where('started_at', '<', now()->subDays(400));
    }
}
