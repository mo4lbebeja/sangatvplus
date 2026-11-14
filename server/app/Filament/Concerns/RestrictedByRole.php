<?php

namespace App\Filament\Concerns;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Role-based access for resources. Admin always has access.
 * Define $viewRoles (who sees the menu) and optionally $manageRoles
 * (who can create/edit/delete; defaults to $viewRoles).
 */
trait RestrictedByRole
{
    /** @param array<UserRole> $roles */
    protected static function userHasRole(array $roles): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->is_active && ($user->isAdmin() || $user->hasRole(...$roles));
    }

    /** @return array<UserRole> */
    protected static function viewRoles(): array
    {
        return [];
    }

    /** @return array<UserRole> */
    protected static function manageRoles(): array
    {
        return static::viewRoles();
    }

    public static function canViewAny(): bool
    {
        return static::userHasRole(static::viewRoles());
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return static::userHasRole(static::manageRoles());
    }

    public static function canEdit(Model $record): bool
    {
        return static::userHasRole(static::manageRoles());
    }

    public static function canDelete(Model $record): bool
    {
        return static::userHasRole(static::manageRoles());
    }

    public static function canDeleteAny(): bool
    {
        return static::userHasRole(static::manageRoles());
    }
}
