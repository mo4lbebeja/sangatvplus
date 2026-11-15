<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Vendor\Impersonation;
use Closure;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admins (who can release every TV and export guest data) must turn on two-factor login before using
 * the CMS: until they do, every page redirects to the profile page where it is set up.
 * (Filament's own "required" switch is evaluated at boot without a user, so it cannot target one role.)
 */
class RequireTwoFactorForAdmins
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Filament::auth()->user();
        // Sijalucom support sessions ("Masuk sebagai") are already protected by the vendor's own two-factor login.
        if (! $user instanceof User || ! $user->isAdmin() || filled($user->getAppAuthenticationSecret()) || Impersonation::active()) {
            return $next($request);
        }

        $profile = Filament::getProfileUrl();
        if ($request->routeIs('filament.admin.auth.profile', 'filament.admin.auth.logout', 'livewire.*') || $request->is('livewire/*', 'livewire-*/*')) {
            return $next($request);
        }

        Notification::make()->title('Aktifkan verifikasi dua langkah')
            ->body('Akun admin wajib memakai aplikasi authenticator (Google Authenticator, Authy). Buka bagian "Autentikasi multi-faktor" di bawah.')
            ->warning()->persistent()->send();

        return redirect()->to($profile);
    }
}
