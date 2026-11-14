<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class AppWhitelist extends Model
{
    use Auditable;

    protected $table = 'app_whitelist';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['clear_on_checkout' => 'boolean', 'is_active' => 'boolean'];
    }
}
