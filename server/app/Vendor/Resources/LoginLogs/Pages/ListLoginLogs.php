<?php

namespace App\Vendor\Resources\LoginLogs\Pages;

use App\Vendor\Resources\LoginLogs\LoginLogResource;
use Filament\Resources\Pages\ListRecords;

class ListLoginLogs extends ListRecords
{
    protected static string $resource = LoginLogResource::class;

    protected ?string $subheading = 'Login Console dan CMS semua hotel. Login Console dari IP baru dan 10× gagal beruntun dilaporkan ke Telegram.';
}
