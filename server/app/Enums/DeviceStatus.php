<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum DeviceStatus: string implements HasColor, HasIcon, HasLabel
{
    case Active = 'active';
    case Revoked = 'revoked';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Revoked => 'Dicabut',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Revoked => 'danger',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Active => Heroicon::OutlinedCheckBadge,
            self::Revoked => Heroicon::OutlinedNoSymbol,
        };
    }
}
