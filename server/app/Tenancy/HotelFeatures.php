<?php

namespace App\Tenancy;

use App\Models\Central\Plan;

/**
 * Plan features enforced in the hotel CMS / API (multi-hotel only). A hotel without a plan (custom price, or
 * single-hotel install) gets everything.
 */
class HotelFeatures
{
    public static function has(?string $feature): bool
    {
        if ($feature === null) {
            return true;
        }
        $hotel = app(HotelManager::class)->current();
        if (! $hotel || ! $hotel->plan_id) {
            return true;
        }
        $features = Plan::query()->whereKey($hotel->plan_id)->value('features');
        $features = is_string($features) ? (json_decode($features, true) ?: []) : (array) $features;

        return $features === [] || in_array($feature, $features, true);
    }
}
