<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Kunjungan teknisi ke hotel (instalasi, perbaikan, perawatan). */
class FieldJob extends Model
{
    protected $connection = 'central';

    protected $guarded = ['id'];

    public const TYPES = ['install' => 'Instalasi', 'repair' => 'Perbaikan', 'maintenance' => 'Perawatan', 'survey' => 'Survei', 'training' => 'Pelatihan staf'];

    public const STATUSES = ['scheduled' => 'Terjadwal', 'in_progress' => 'Dikerjakan', 'done' => 'Selesai', 'cancelled' => 'Batal'];

    public function technician(): BelongsTo
    {
        return $this->belongsTo(VendorUser::class, 'technician_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }
}
