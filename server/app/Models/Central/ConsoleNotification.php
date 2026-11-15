<?php

namespace App\Models\Central;

use Illuminate\Notifications\DatabaseNotification;

/** Bell notifications of the Sijalucom Console (central database, not a hotel's). */
class ConsoleNotification extends DatabaseNotification
{
    protected $connection = 'central';
}
