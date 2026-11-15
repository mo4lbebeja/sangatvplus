<?php

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Models\HotelProfile;
use App\Models\User;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class WelcomeHero extends Widget
{
    /** Render with the page: no loading placeholder that later changes height (layout jump). */
    protected static bool $isLazy = false;

    protected static ?int $sort = -10;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.welcome-hero';

    protected function getViewData(): array
    {
        /** @var User $user */
        $user = Auth::user();
        $hour = (int) now()->timezone(HotelProfile::current()->timezone)->format('H');
        $greeting = match (true) {
            $hour < 11 => 'Selamat pagi',
            $hour < 15 => 'Selamat siang',
            $hour < 18 => 'Selamat sore',
            default => 'Selamat malam',
        };

        // Quick actions follow the user's role.
        $links = collect([
            ['Check-in tamu', '/admin/guests', 'M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.03 5.91c-.57-.1-1.17.03-1.58.44L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.82c0-.6.24-1.17.66-1.59l6.4-6.4c.41-.41.54-1.01.44-1.58a6 6 0 1 1 11.75-1.11Z', [UserRole::FrontOffice]],
            ['Slide beranda', '/admin/slides', 'm2.25 15.75 5.16-5.16a2.25 2.25 0 0 1 3.18 0l5.16 5.16m-1.5-1.5 1.41-1.41a2.25 2.25 0 0 1 3.18 0l2.91 2.91M3.75 21h16.5A1.5 1.5 0 0 0 21.75 19.5v-15A1.5 1.5 0 0 0 20.25 3H3.75A1.5 1.5 0 0 0 2.25 4.5v15A1.5 1.5 0 0 0 3.75 21Z', [UserRole::FrontOffice]],
            ['Channel', '/admin/channels', 'M9.35 15.65a3.75 3.75 0 0 1 0-5.3m5.3 0a3.75 3.75 0 0 1 0 5.3M7.23 17.77a6.75 6.75 0 0 1 0-9.54m9.54 0a6.75 6.75 0 0 1 0 9.54M12 12.75h.01', [UserRole::It]],
            ['TV terdaftar', '/admin/devices', 'M6 20.25h12m-7.5-3v3m3-3v3m-10.13-3h17.25c.62 0 1.13-.5 1.13-1.13V4.88c0-.62-.5-1.13-1.13-1.13H3.38c-.62 0-1.13.5-1.13 1.13v11.25c0 .62.5 1.12 1.13 1.12Z', [UserRole::It]],
        ])->filter(fn ($l) => $user->isAdmin() || $user->hasRole(...$l[3]))->values();

        return [
            'greeting' => $greeting,
            'name' => $user->name,
            'role' => $user->role->getLabel(),
            'hotel' => HotelProfile::current()->name,
            'date' => now()->timezone(HotelProfile::current()->timezone)->locale('id')->translatedFormat('l, j F Y'),
            'links' => $links,
        ];
    }
}
