<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\ImageOptimizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class GalleryPhoto extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    protected static function booted(): void
    {
        // Full image ≤1920px for the viewer + a 480px thumbnail for the grid.
        static::saving(function (GalleryPhoto $p) {
            if ($p->isDirty('image_path') && $p->image_path && ! str_starts_with($p->image_path, 'http')) {
                if ($old = $p->getOriginal('thumb_path')) {
                    Storage::disk('public')->delete($old);
                }
                $p->image_path = ImageOptimizer::fit('public', $p->image_path, 1920);
                $p->thumb_path = ImageOptimizer::thumbnail('public', $p->image_path, 480);
            }
        });
        static::deleted(function (GalleryPhoto $p) {
            Storage::disk('public')->delete(array_filter([$p->image_path, $p->thumb_path]));
        });
    }

    public function scopeVisible(Builder $q): Builder
    {
        return $q->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }
}
