<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PmsIntegration extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected $hidden = ['secret'];

    protected array $auditExclude = ['last_used_at'];

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'allowed_ips' => 'array',
            'auto_create_rooms' => 'boolean',
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(PmsEvent::class);
    }

    public static function newKeyId(): string
    {
        return 'pms_'.Str::lower(Str::random(20));
    }

    public static function newSecret(): string
    {
        return Str::random(48);
    }

    public function ipAllowed(?string $ip): bool
    {
        $list = array_filter((array) $this->allowed_ips);

        return $list === [] || in_array($ip, $list, true);
    }

    /** HMAC-SHA256 over "{timestamp}.{METHOD}.{path}.{rawBody}", hex encoded. */
    public function signatureFor(string $timestamp, string $method, string $path, string $body): string
    {
        return hash_hmac('sha256', $timestamp.'.'.strtoupper($method).'.'.$path.'.'.$body, (string) $this->secret);
    }
}
