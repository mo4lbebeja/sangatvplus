<?php

namespace App\Models;

use App\Enums\ProviderSigning;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChannelProvider extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    /** Credentials stay on the server: never serialized, never audited. */
    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return [
            'signing' => ProviderSigning::class,
            'credentials' => 'encrypted:array',
            'is_active' => 'boolean',
        ];
    }

    public function channels(): HasMany
    {
        return $this->hasMany(Channel::class);
    }
}
