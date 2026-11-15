<?php

namespace App\Providers\Filament;

use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Sijalucom vendor panel (/sijalucom): every hotel renting SangaTV Plus — create hotels, rental period,
 * suspend, TVs online, app versions, backups. Separate accounts (central database), two-factor required.
 * No hotel database is opened here except briefly to read numbers.
 */
class VendorPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('vendor')
            ->path('sijalucom')
            ->authGuard('vendor')
            ->login()
            ->profile(isSimple: false)
            ->multiFactorAuthentication([AppAuthentication::make()->recoverable()->brandName('SangaTV Plus Vendor')], isRequired: true)
            ->brandName('SangaTV Plus · Sijalucom')
            ->favicon(asset('icons/favicon-64.png'))
            ->font('Plus Jakarta Sans', url: asset('css/sangatv-fonts.css'), provider: LocalFontProvider::class)
            ->colors(['primary' => Color::Indigo, 'gray' => Color::Slate, 'danger' => Color::Rose, 'warning' => Color::Amber, 'success' => Color::Emerald])
            ->maxContentWidth(Width::Full)
            ->spa()
            ->discoverResources(in: app_path('Vendor/Resources'), for: 'App\Vendor\Resources')
            ->discoverWidgets(in: app_path('Vendor/Widgets'), for: 'App\Vendor\Widgets')
            ->pages([Dashboard::class])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([Authenticate::class]);
    }
}
