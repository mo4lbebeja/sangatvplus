<?php

namespace App\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum FacilityCategory: string implements HasIcon, HasLabel
{
    case Facility = 'facility';
    case Dining = 'dining';

    public function getLabel(): string
    {
        return match ($this) {
            self::Facility => 'Fasilitas',
            self::Dining => 'Restoran & Room Service',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Facility => Heroicon::OutlinedSparkles,
            self::Dining => Heroicon::OutlinedCake,
        };
    }
}
