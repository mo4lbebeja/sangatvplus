<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A rental package (monthly price = base + per extra TV). */
class Plan extends Model
{
    protected $connection = 'central';

    protected $guarded = ['id'];

    public const FEATURES = [
        'tv_live' => 'TV Live & EPG',
        'launcher' => 'Launcher hotel & sambutan tamu',
        'apps' => 'Aplikasi streaming terkelola',
        'info' => 'Info hotel, fasilitas, room service',
        'pms' => 'Integrasi PMS (check-in/out otomatis)',
        'ota' => 'Update OTA & pemantauan TV',
        'stats' => 'Statistik tontonan & uptime',
        'support' => 'Dukungan prioritas',
    ];

    protected function casts(): array
    {
        return ['features' => 'array', 'is_active' => 'boolean'];
    }

    public function hotels(): HasMany
    {
        return $this->hasMany(Hotel::class);
    }

    /** Monthly price for a number of TVs. */
    public function monthlyFor(?int $tvs): int
    {
        $extra = max(0, (int) $tvs - $this->included_tvs);

        return $this->base_price + $extra * $this->price_per_tv;
    }
}
