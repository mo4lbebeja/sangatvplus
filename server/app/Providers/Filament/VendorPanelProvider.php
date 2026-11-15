<?php

namespace App\Providers\Filament;

use App\Vendor\Pages\Dashboard;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Sijalucom Console (/sijalucom): the owner's control room for every hotel renting SangaTV Plus —
 * clients, monthly rentals, invoices, business analytics, reports, staff. Separate accounts, two-factor required.
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
            ->multiFactorAuthentication([AppAuthentication::make()->recoverable()->brandName('Sijalucom Console')], isRequired: true)
            ->brandName('Sijalucom Console')
            ->brandLogo(fn () => view('vendor-console.brand'))
            ->brandLogoHeight('2.25rem')
            ->favicon(asset('icons/favicon-64.png'))
            ->font('Plus Jakarta Sans', url: asset('css/sangatv-fonts.css'), provider: LocalFontProvider::class)
            ->colors([
                'primary' => Color::Indigo,
                'gray' => Color::Slate,
                'info' => Color::Sky,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Rose,
            ])
            ->sidebarWidth('15.5rem')
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth(Width::Full)
            ->spa()
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->databaseNotifications()
            ->databaseNotificationsPolling('60s')
            ->navigationGroups([
                NavigationGroup::make('Klien'),
                NavigationGroup::make('Keuangan'),
                NavigationGroup::make('Analisa'),
                NavigationGroup::make('Operasional'),
                NavigationGroup::make('Sistem'),
            ])
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => view('vendor-console.head'))
            ->renderHook(PanelsRenderHook::PAGE_HEADER_HEADING_BEFORE, fn () => view('vendor-console.heading-icon'))
            ->renderHook(PanelsRenderHook::SIDEBAR_FOOTER, fn () => view('vendor-console.sidebar-footer'))
            ->renderHook(PanelsRenderHook::GLOBAL_SEARCH_BEFORE, fn () => view('vendor-console.topbar'))
            ->discoverResources(in: app_path('Vendor/Resources'), for: 'App\Vendor\Resources')
            ->discoverPages(in: app_path('Vendor/Pages'), for: 'App\Vendor\Pages')
            ->discoverWidgets(in: app_path('Vendor/Widgets'), for: 'App\Vendor\Widgets')
            ->pages([Dashboard::class])
            ->widgets([])
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
