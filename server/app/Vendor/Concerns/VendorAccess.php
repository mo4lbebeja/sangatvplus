<?php

namespace App\Vendor\Concerns;

use App\Models\Central\VendorUser;
use Filament\Facades\Filament;

/** Vendor console roles: a resource/page declares its area ("finance", "support" or "owner"). */
trait VendorAccess
{
    protected static function area(): string
    {
        return 'support';
    }

    public static function vendor(): ?VendorUser
    {
        $user = Filament::auth()->user();

        return $user instanceof VendorUser ? $user : null;
    }

    public static function allowed(?string $area = null): bool
    {
        return (bool) static::vendor()?->hasAccess($area ?? static::area());
    }

    public static function canAccess(): bool
    {
        return static::allowed();
    }

    public static function canViewAny(): bool
    {
        return static::allowed();
    }
}
