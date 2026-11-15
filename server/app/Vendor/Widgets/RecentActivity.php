<?php

namespace App\Vendor\Widgets;

use App\Models\Central\VendorActivity;
use Filament\Widgets\Widget;

class RecentActivity extends Widget
{
    protected static bool $isLazy = false;

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = ['md' => 1, 'xl' => 1];

    protected string $view = 'vendor-console.widgets.recent-activity';

    protected ?string $pollingInterval = '60s';

    public const ICONS = [
        'invoice.paid' => ['heroicon-o-check-badge', 'emerald'], 'invoice.created' => ['heroicon-o-document-plus', 'indigo'],
        'invoice.sent' => ['heroicon-o-paper-airplane', 'sky'], 'invoice.void' => ['heroicon-o-x-circle', 'slate'],
        'rental.extended' => ['heroicon-o-calendar-days', 'violet'], 'rental.trial' => ['heroicon-o-beaker', 'cyan'],
        'hotel.created' => ['heroicon-o-building-office-2', 'indigo'], 'hotel.suspended' => ['heroicon-o-no-symbol', 'rose'],
        'hotel.activated' => ['heroicon-o-check-circle', 'emerald'], 'service.released' => ['heroicon-o-power', 'rose'],
        'support.login' => ['heroicon-o-arrow-right-end-on-rectangle', 'amber'], 'ticket.created' => ['heroicon-o-lifebuoy', 'rose'],
        'tv.command' => ['heroicon-o-command-line', 'sky'], 'notice.published' => ['heroicon-o-megaphone', 'amber'],
    ];

    protected function getViewData(): array
    {
        return ['items' => VendorActivity::query()->with(['hotel:id,name', 'user:id,name'])->latest('created_at')->limit(6)->get()];
    }
}
