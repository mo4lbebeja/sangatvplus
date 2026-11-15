<?php

namespace App\Listeners;

use App\Models\Central\LoginLog;
use App\Models\Central\VendorUser;
use App\Services\Notifier;
use App\Tenancy\HotelManager;
use App\Vendor\ConsoleNotify;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Throwable;

/** Console & CMS sign-ins (central login_logs); a Console login from a new IP alerts Sijalucom. */
class RecordLogin
{
    public function handle(Login|Failed $event): void
    {
        $tenancy = app(HotelManager::class);
        if (! $tenancy->enabled() || ! in_array($event->guard, ['vendor', 'web'], true)) {
            return;
        }
        try {
            $email = $event->user?->email ?? ($event instanceof Failed ? ($event->credentials['email'] ?? null) : null);
            $ip = request()->ip();
            $success = $event instanceof Login;
            $newIp = $success && $email && LoginLog::query()->where('email', $email)->where('guard', $event->guard)->where('success', true)->exists()
                && ! LoginLog::query()->where('email', $email)->where('guard', $event->guard)->where('ip', $ip)->where('success', true)->exists();
            LoginLog::query()->create([
                'guard' => $event->guard, 'hotel_id' => $event->guard === 'web' ? $tenancy->current()?->id : null, 'email' => $email,
                'success' => $success, 'new_ip' => $newIp, 'ip' => $ip, 'user_agent' => substr((string) request()->userAgent(), 0, 255),
            ]);
            if ($newIp && $event->user instanceof VendorUser) {
                ConsoleNotify::all('Login Console dari IP baru', "{$email} masuk dari {$ip}.", 'heroicon-o-finger-print', 'warning', 'owner');
                rescue(fn () => app(Notifier::class)->sendToVendor('🔐 Login Console dari IP baru', "{$email} masuk dari {$ip}\n".request()->userAgent()), report: false);
            }
            if (! $success && LoginLog::query()->where('ip', $ip)->where('success', false)->where('created_at', '>', now()->subMinutes(15))->count() === 10) {
                rescue(fn () => app(Notifier::class)->sendToVendor('🚨 Percobaan login gagal berulang', "10× gagal dalam 15 menit dari {$ip} ({$event->guard})."), report: false);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
