<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/** Sijalucom company profile & invoice settings (key → JSON value). */
class VendorSetting extends Model
{
    protected $connection = 'central';

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }

    public const DEFAULTS = [
        'company_name' => 'Sijalucom',
        'company_address' => '',
        'company_phone' => '',
        'company_email' => 'sijalucom@gmail.com',
        'company_npwp' => '',
        'bank_accounts' => '',
        'invoice_prefix' => 'INV',
        'tax_percent' => 0,
        'due_days' => 7,
        'invoice_days_before' => 14,
        'invoice_footer' => 'Terima kasih atas kepercayaan Anda menggunakan SangaTV Plus.',
        // Collections
        'reminder_days' => '7,3,0,-3,-7', // days before (positive) / after (negative) the due date
        'suspend_after_days' => 0, // auto-suspend a hotel N days after a due date (0 = off)
        // Online payments (filled in Console → Pengaturan → Integrasi)
        'payment_gateway' => 'none', // none | xendit | midtrans
        'xendit_secret_key' => null,
        'xendit_callback_token' => null,
        'midtrans_server_key' => null,
        'midtrans_production' => false,
        // WhatsApp gateway
        'whatsapp_provider' => 'none', // none | fonnte | wablas | custom
        'whatsapp_token' => null,
        'whatsapp_url' => null, // wablas domain or custom endpoint
    ];

    /** Stored encrypted (APP_KEY); never shown back in full. */
    public const SECRETS = ['xendit_secret_key', 'xendit_callback_token', 'midtrans_server_key', 'whatsapp_token'];

    public static function secret(string $key): ?string
    {
        $v = self::allSettings()[$key] ?? null;

        return blank($v) ? null : rescue(fn () => Crypt::decryptString($v), null, false);
    }

    public static function allSettings(): array
    {
        return Cache::remember('vendor:settings', 300, fn () => array_merge(self::DEFAULTS,
            rescue(fn () => self::query()->pluck('value', 'key')->all(), [], false)));
    }

    public static function read(string $key): mixed
    {
        return self::allSettings()[$key] ?? null;
    }

    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            if (in_array($key, self::SECRETS, true)) {
                if (blank($value)) {
                    continue; // empty field = keep the stored secret
                }
                $value = Crypt::encryptString((string) $value);
            }
            self::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
        Cache::forget('vendor:settings');
    }
}
