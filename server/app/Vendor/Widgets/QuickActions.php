<?php

namespace App\Vendor\Widgets;

use Filament\Widgets\Widget;

class QuickActions extends Widget
{
    protected static bool $isLazy = false;

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'vendor-console.widgets.quick-actions';
}
