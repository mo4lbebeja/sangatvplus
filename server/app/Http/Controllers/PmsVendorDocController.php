<?php

namespace App\Http\Controllers;

use App\Filament\Pages\PmsIntegrationPage;
use Illuminate\Http\Response;

/** Vendor-facing PMS API document (Markdown download) — Admin & IT only. */
class PmsVendorDocController extends Controller
{
    public function __invoke(): Response
    {
        abort_unless(PmsIntegrationPage::canAccess(), 403);

        return response(PmsIntegrationPage::vendorMarkdown(), 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="SangaTV-Plus-API-PMS.md"',
        ]);
    }
}
