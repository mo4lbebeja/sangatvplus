<?php

namespace App\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum CommandType: string implements HasIcon, HasLabel
{
    case ClearAppData = 'clear_app_data';
    case Refresh = 'refresh';
    case Reboot = 'reboot';
    case HideStockLauncher = 'hide_stock_launcher';
    case ShowStockLauncher = 'show_stock_launcher';
    case CheckUpdate = 'check_update';

    public function getLabel(): string
    {
        return match ($this) {
            self::ClearAppData => 'Hapus data aplikasi tamu',
            self::Refresh => 'Muat ulang konten',
            self::Reboot => 'Restart TV',
            self::HideStockLauncher => 'Sembunyikan launcher bawaan (Device Owner)',
            self::ShowStockLauncher => 'Munculkan launcher bawaan (Device Owner)',
            self::CheckUpdate => 'Cek & pasang update aplikasi sekarang',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::ClearAppData => Heroicon::OutlinedTrash,
            self::Refresh => Heroicon::OutlinedArrowPath,
            self::Reboot => Heroicon::OutlinedPower,
            self::HideStockLauncher => Heroicon::OutlinedEyeSlash,
            self::ShowStockLauncher => Heroicon::OutlinedEye,
            self::CheckUpdate => Heroicon::OutlinedArrowDownTray,
        };
    }

    /** Commands Android only allows for Device Owner TVs. */
    public function requiresDeviceOwner(): bool
    {
        return in_array($this, [self::Reboot, self::HideStockLauncher, self::ShowStockLauncher], true);
    }
}
