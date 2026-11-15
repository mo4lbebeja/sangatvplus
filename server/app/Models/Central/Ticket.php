<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Support request from a hotel to Sijalucom. */
class Ticket extends Model
{
    protected $connection = 'central';

    protected $guarded = ['id'];

    public const CATEGORIES = ['technical' => 'Teknis / TV', 'billing' => 'Tagihan', 'content' => 'Konten & channel', 'request' => 'Permintaan fitur', 'other' => 'Lainnya'];

    public const PRIORITIES = ['low' => 'Rendah', 'normal' => 'Normal', 'high' => 'Tinggi', 'urgent' => 'Mendesak'];

    public const STATUSES = ['open' => 'Baru', 'in_progress' => 'Diproses', 'waiting' => 'Menunggu hotel', 'resolved' => 'Selesai', 'closed' => 'Ditutup'];

    protected function casts(): array
    {
        return ['last_reply_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public static function nextNumber(): string
    {
        $base = 'TKT-'.now('Asia/Jakarta')->format('ym').'-';
        $last = self::query()->where('number', 'like', $base.'%')->orderByDesc('number')->value('number');

        return $base.str_pad((string) ((int) substr((string) $last, strlen($base)) + 1), 3, '0', STR_PAD_LEFT);
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(VendorUser::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class)->orderBy('id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['open', 'in_progress', 'waiting'], true);
    }
}
