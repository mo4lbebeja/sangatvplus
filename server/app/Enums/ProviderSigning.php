<?php

namespace App\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum ProviderSigning: string implements HasIcon, HasLabel
{
    case None = 'none';
    case HmacQuery = 'hmac_query';

    public function getLabel(): string
    {
        return match ($this) {
            self::None => 'Tanpa tanda tangan',
            self::HmacQuery => 'HMAC (query ?expires=&token=)',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::None => Heroicon::OutlinedLockOpen,
            self::HmacQuery => Heroicon::OutlinedLockClosed,
        };
    }
}
