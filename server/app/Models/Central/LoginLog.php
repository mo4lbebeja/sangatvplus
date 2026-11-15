<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Riwayat login Console & CMS. */
class LoginLog extends Model
{
    protected $connection = 'central';

    protected $guarded = ['id'];

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['success' => 'boolean', 'new_ip' => 'boolean', 'created_at' => 'datetime'];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }
}
