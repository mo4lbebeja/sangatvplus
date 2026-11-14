<?php

namespace App\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum UserRole: string implements HasIcon, HasLabel
{
    case Admin = 'admin';
    case FrontOffice = 'front_office';
    case It = 'it';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::FrontOffice => 'Front Office',
            self::It => 'IT',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Admin => Heroicon::OutlinedShieldCheck,
            self::FrontOffice => Heroicon::OutlinedUserCircle,
            self::It => Heroicon::OutlinedWrenchScrewdriver,
        };
    }
}
