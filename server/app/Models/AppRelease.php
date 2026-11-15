<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class AppRelease extends Model
{
    use Auditable;

    public const DEFAULT_PACKAGE = 'com.sangatv.plus.staging';

    public const PACKAGES = [
        'com.sangatv.plus' => 'Rilis (com.sangatv.plus)',
        'com.sangatv.plus.staging' => 'Staging (com.sangatv.plus.staging)',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'version_code' => 'integer'];
    }

    /** Only one active release per package: activating this one retires the others of the same package. */
    protected static function booted(): void
    {
        static::saved(function (AppRelease $r) {
            if ($r->is_active) {
                static::query()->whereKeyNot($r->getKey())->where('package_name', $r->package_name)->where('is_active', true)->update(['is_active' => false]);
            }
        });
    }
}
