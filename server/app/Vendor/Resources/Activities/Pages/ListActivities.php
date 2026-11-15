<?php

namespace App\Vendor\Resources\Activities\Pages;

use App\Vendor\Resources\Activities\ActivityResource;
use Filament\Resources\Pages\ListRecords;

class ListActivities extends ListRecords
{
    protected static string $resource = ActivityResource::class;

    protected ?string $subheading = 'Semua tindakan tim Sijalucom dan sistem: aktivasi, pembayaran, penangguhan, masuk CMS hotel.';
}
