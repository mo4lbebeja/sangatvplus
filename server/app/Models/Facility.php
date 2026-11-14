<?php

namespace App\Models;

use App\Enums\FacilityCategory;
use App\Models\Concerns\Auditable;
use App\Support\ImageOptimizer;
use Illuminate\Database\Eloquent\Model;

class Facility extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::saving(function (Facility $f) {
            if ($f->isDirty('image_path') && $f->image_path && ! str_starts_with($f->image_path, 'http')) {
                $f->image_path = ImageOptimizer::fit('public', $f->image_path, 960);
            }
        });
    }

    protected function casts(): array
    {
        return ['category' => FacilityCategory::class, 'is_active' => 'boolean'];
    }
}
