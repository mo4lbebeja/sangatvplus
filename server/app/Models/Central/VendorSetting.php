<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

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
    ];

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
            self::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
        Cache::forget('vendor:settings');
    }
}
