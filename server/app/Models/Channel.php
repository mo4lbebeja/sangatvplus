<?php

namespace App\Models;

use App\Enums\SourceType;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Channel extends Model
{
    use Auditable, HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'source_type' => SourceType::class,
            'drm' => 'array',
            'is_active' => 'boolean',
            'number' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ChannelCategory::class, 'channel_category_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(ChannelProvider::class, 'channel_provider_id');
    }
}
