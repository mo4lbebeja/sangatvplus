<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;

class Dashboard extends BaseDashboard
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $title = 'Ringkasan';

    protected static ?string $navigationLabel = 'Ringkasan';

    public function getColumns(): int|array
    {
        return ['md' => 2, 'xl' => 4];
    }
}
