<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    protected $connection = 'central';

    protected $guarded = ['id'];

    public const STATUSES = ['draft' => 'Draf', 'unpaid' => 'Belum dibayar', 'paid' => 'Lunas', 'void' => 'Dibatalkan'];

    public const METHODS = ['transfer' => 'Transfer bank', 'qris' => 'QRIS', 'cash' => 'Tunai', 'xendit' => 'Online (Xendit)', 'midtrans' => 'Online (Midtrans)', 'other' => 'Lainnya'];

    protected function casts(): array
    {
        return [
            'items' => 'array', 'period_start' => 'date', 'period_end' => 'date', 'issued_at' => 'date', 'due_at' => 'date',
            'paid_at' => 'datetime', 'applied_at' => 'datetime', 'tax_percent' => 'float', 'reminders' => 'array',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(VendorUser::class, 'created_by');
    }

    public function isOverdue(): bool
    {
        return $this->status === 'unpaid' && $this->due_at->lt(now('Asia/Jakarta')->startOfDay());
    }

    /** "paid" | "overdue" | "unpaid" | "draft" | "void" (overdue is computed, never stored). */
    public function displayStatus(): string
    {
        return $this->isOverdue() ? 'overdue' : $this->status;
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereIn('status', ['unpaid', 'draft']);
    }

    public function scopeOverdue(Builder $q): Builder
    {
        return $q->where('status', 'unpaid')->whereDate('due_at', '<', now('Asia/Jakarta')->toDateString());
    }
}
