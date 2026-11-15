<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;

/** PMS API key id → hotel (the key header arrives before the hotel database is open). */
class PmsKeyIndex extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $connection = 'central';

    protected $table = 'pms_key_index';

    protected $primaryKey = 'key_id';

    protected $keyType = 'string';

    protected $guarded = [];
}
