<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\ImageOptimizer;
use App\Support\MediaUrl;
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

        // Only one slide can be the Apps-screen background: turning it on elsewhere turns the others off.
        static::saved(function (Slide $slide) {
            if ($slide->is_apps_background && $slide->wasChanged('is_apps_background')) {
                static::query()->whereKeyNot($slide->getKey())->where('is_apps_background', true)->update(['is_apps_background' => false]);
            }
        });
    }

    /** Background image URL for the TV menu screens (Aplikasi, Info Hotel …), or null for the plain theme background. */
    public static function appsBackgroundUrl(): ?string
    {
        return MediaUrl::of(static::query()->where('is_apps_background', true)->whereNotNull('image_path')->value('image_path'));
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_apps_background' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function scopeVisible(Builder $q): Builder
    {
        return $q->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }
}
