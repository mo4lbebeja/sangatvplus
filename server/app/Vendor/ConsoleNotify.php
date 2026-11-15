<?php

namespace App\Vendor;

use App\Models\Central\VendorUser;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Throwable;

/** Bell notifications for Sijalucom staff (optionally only those with access to an area). */
class ConsoleNotify
{
    public static function all(string $title, string $body, string $icon = 'heroicon-o-bell', string $color = 'info', ?string $area = null, ?string $url = null): void
    {
        try {
            $users = VendorUser::query()->where('is_active', true)->get()->filter(fn (VendorUser $u) => $area === null || $u->hasAccess($area));
            foreach ($users as $u) {
                $n = Notification::make()->title($title)->body($body)->icon($icon)->iconColor($color);
                if ($url) {
                    $n->actions([Action::make('open')->label('Buka')->url($url)->markAsRead()]);
                }
                $n->sendToDatabase($u);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
