<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChannelCategory extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    public function channels(): HasMany
    {
        return $this->hasMany(Channel::class);
    }
}
