<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Prospek hotel (pipeline penjualan). */
class Lead extends Model
{
    protected $connection = 'central';

    protected $guarded = ['id'];

    public const STAGES = ['new' => 'Baru', 'contacted' => 'Dihubungi', 'demo' => 'Demo / uji coba', 'negotiation' => 'Negosiasi', 'won' => 'Jadi klien', 'lost' => 'Batal'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(VendorUser::class, 'owner_id');
    }

    protected function casts(): array
    {
        return ['next_follow_up' => 'date', 'estimated_value' => 'integer'];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }
}
