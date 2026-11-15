<?php

namespace App\Http\Controllers;

use App\Filament\Pages\HotelProfilePage;
use App\Models\HotelProfile;
use App\Services\HotelDataExport;
use App\Support\Audit;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** ZIP of the hotel's data — Admin only (same rule as Profil Hotel). */
class HotelExportController extends Controller
{
    public function __invoke(HotelDataExport $export): BinaryFileResponse
    {
        abort_unless(HotelProfilePage::canAccess(), 403);
        Audit::log('hotel.data_exported', HotelProfile::current());

        $name = 'sangatv-plus-'.Str::slug(HotelProfile::current()->name).'-'.now()->format('Ymd-His').'.zip';

        return response()->download($export->build(), $name, ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
    }
}
