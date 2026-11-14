<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\ImageOptimizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Slide extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        // Every slide image becomes a 1920×1080 progressive JPEG (light for 1 GB TVs).
        static::saving(function (Slide $slide) {
            if ($slide->isDirty('image_path') && $slide->image_path && ! str_starts_with($slide->image_path, 'http')) {
                $slide->image_path = ImageOptimizer::cover('public', $slide->image_path, 1920, 1080);
            }
        });
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function scopeVisible(Builder $q): Builder
    {
        return $q->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }
}
