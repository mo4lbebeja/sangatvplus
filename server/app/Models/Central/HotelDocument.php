<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** Contract / MoU / handover document of a hotel (private storage). */
class HotelDocument extends Model
{
    protected $connection = 'central';

    protected $guarded = ['id'];

    public const TYPES = ['contract' => 'Kontrak sewa', 'mou' => 'MoU / PKS', 'bast' => 'Berita acara (BAST)', 'invoice' => 'Faktur / pajak', 'other' => 'Lainnya'];

    protected function casts(): array
    {
        return ['signed_at' => 'date', 'expires_at' => 'date'];
    }

    protected static function booted(): void
    {
        static::deleted(fn (HotelDocument $d) => Storage::disk('local')->delete($d->path));
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(VendorUser::class, 'uploaded_by');
    }
}
