<?php

namespace App\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum SourceType: string implements HasIcon, HasLabel
{
    case LocalMulticast = 'local_multicast';
    case LocalHls = 'local_hls';
    case ProviderHls = 'provider_hls';
    case ProviderDash = 'provider_dash';

    public function getLabel(): string
    {
        return match ($this) {
            self::LocalMulticast => 'Lokal – UDP multicast',
            self::LocalHls => 'Lokal – HLS',
            self::ProviderHls => 'Penyedia – HLS',
            self::ProviderDash => 'Penyedia – DASH',
        };
    }

    public function isProvider(): bool
    {
        return in_array($this, [self::ProviderHls, self::ProviderDash], true);
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::LocalMulticast => Heroicon::OutlinedSignal,
            self::LocalHls => Heroicon::OutlinedServerStack,
            self::ProviderHls => Heroicon::OutlinedGlobeAlt,
            self::ProviderDash => Heroicon::OutlinedCloud,
        };
    }
}
