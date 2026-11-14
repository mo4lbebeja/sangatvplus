<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum GuestStatus: string implements HasColor, HasIcon, HasLabel
{
    case InHouse = 'in_house';
    case CheckedOut = 'checked_out';

    public function getLabel(): string
    {
        return match ($this) {
            self::InHouse => 'Menginap',
            self::CheckedOut => 'Check-out',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::InHouse => 'success',
            self::CheckedOut => 'gray',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::InHouse => Heroicon::OutlinedKey,
            self::CheckedOut => Heroicon::OutlinedArrowRightStartOnRectangle,
        };
    }
}
