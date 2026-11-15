<?php

namespace App\Models\Central;

use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Foundation\Auth\User as Authenticatable;

/** Sijalucom staff (vendor panel). Lives in the central database, separate from every hotel's CMS accounts. */
#[Fillable(['name', 'email', 'role', 'password', 'is_active'])]
#[Hidden(['password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes'])]
class VendorUser extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery
{
    protected $connection = 'central';

    protected $attributes = ['is_active' => true, 'role' => 'owner'];

    public const ROLES = ['owner' => 'Pemilik (semua akses)', 'finance' => 'Keuangan (tagihan & laporan)', 'support' => 'Dukungan (hotel & TV)'];

    /** owner: everything · finance: invoices, plans, reports · support: hotels, TVs, support logins. */
    public function hasAccess(string $area): bool
    {
        return match ($area) {
            'finance' => in_array($this->role, ['owner', 'finance'], true),
            'support' => in_array($this->role, ['owner', 'support'], true),
            default => $this->role === 'owner',
        };
    }

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'vendor' && $this->is_active;
    }

    public function getAppAuthenticationSecret(): ?string
    {
        return $this->app_authentication_secret;
    }

    public function saveAppAuthenticationSecret(#[\SensitiveParameter] ?string $secret): void
    {
        $this->app_authentication_secret = $secret;
        $this->save();
    }

    public function getAppAuthenticationHolderName(): string
    {
        return $this->email;
    }

    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->app_authentication_recovery_codes;
    }

    public function saveAppAuthenticationRecoveryCodes(#[\SensitiveParameter] ?array $codes): void
    {
        $this->app_authentication_recovery_codes = $codes;
        $this->save();
    }
}
