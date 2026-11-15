<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Perangkat milik Sijalucom (TV, STB, remote) di gudang atau terpasang di hotel. */
class Asset extends Model
{
    protected $connection = 'central';

    protected $guarded = ['id'];

    public const TYPES = ['tv' => 'TV', 'stb' => 'STB / Android box', 'remote' => 'Remote', 'router' => 'Router / AP', 'other' => 'Lainnya'];

    public const STATUSES = ['in_stock' => 'Di gudang', 'installed' => 'Terpasang', 'repair' => 'Perbaikan', 'retired' => 'Afkir'];

    protected function casts(): array
    {
        return ['purchased_at' => 'date', 'warranty_until' => 'date', 'price' => 'integer'];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }
}
