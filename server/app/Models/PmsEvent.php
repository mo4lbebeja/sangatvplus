<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Log of every request from the PMS (kept 90 days; contains guest names). */
class PmsEvent extends Model
{
    use Prunable;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'response' => 'array', 'created_at' => 'datetime'];
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(PmsIntegration::class, 'pms_integration_id');
    }

    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(90));
    }
}
