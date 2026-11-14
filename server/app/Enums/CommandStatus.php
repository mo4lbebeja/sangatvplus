<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum CommandStatus: string implements HasColor, HasIcon, HasLabel
{
    case Pending = 'pending';
    case Delivered = 'delivered';
    case Done = 'done';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Delivered => 'Terkirim',
            self::Done => 'Selesai',
            self::Failed => 'Gagal',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Delivered => 'info',
            self::Done => 'success',
            self::Failed => 'danger',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Pending => Heroicon::OutlinedClock,
            self::Delivered => Heroicon::OutlinedPaperAirplane,
            self::Done => Heroicon::OutlinedCheckCircle,
            self::Failed => Heroicon::OutlinedExclamationTriangle,
        };
    }
}
