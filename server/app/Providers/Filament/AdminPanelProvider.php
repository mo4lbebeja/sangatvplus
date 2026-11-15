<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Http\Middleware\RequireTwoFactorForAdmins;
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
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->profile(isSimple: false)
            // Two-factor login with an authenticator app (Google Authenticator, Authy …). Admins are forced to
            // set it up by RequireTwoFactorForAdmins; other roles may turn it on in their profile.
            ->multiFactorAuthentication(
                [AppAuthentication::make()->recoverable()->brandName('SangaTV Plus')],
                isRequired: fn () => Auth::user()?->isAdmin() ?? false,
            )
            ->brandName('SangaTV Plus')
            ->brandLogo(fn () => view('filament.brand'))
            ->brandLogoHeight('2.25rem')
            ->favicon(asset('icons/favicon-64.png').'?v='.@filemtime(public_path('icons/favicon-64.png')))
            ->font('Plus Jakarta Sans', url: asset('css/sangatv-fonts.css'), provider: LocalFontProvider::class)
            ->colors([
                'primary' => Color::hex('#38C6D9'),
                'gray' => Color::Slate,
                'info' => Color::Sky,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Rose,
            ])
            ->sidebarWidth('16.5rem')
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth(Width::Full)
            ->spa()
            ->unsavedChangesAlerts()
            ->navigationGroups([
                NavigationGroup::make('Tamu & Kamar'),
                NavigationGroup::make('Konten'),
                NavigationGroup::make('Siaran'),
                NavigationGroup::make('Perangkat'),
                NavigationGroup::make('Integrasi PMS')->collapsed(),
                NavigationGroup::make('Sistem')->collapsed(),
            ])
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => view('filament.pwa-head'))
            ->renderHook(PanelsRenderHook::BODY_END, fn () => view('filament.pwa-body'))
            ->renderHook(PanelsRenderHook::CONTENT_START, fn () => view('filament.service-banner'))
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([Dashboard::class])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
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
            ->authMiddleware([
                Authenticate::class,
                RequireTwoFactorForAdmins::class,
            ]);
    }
}
