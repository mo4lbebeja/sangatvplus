<?php

namespace App\Http\Controllers;

use App\Models\Central\Hotel;
use App\Vendor\Guides\PmsGuide;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/** PMS API document (Markdown) for a hotel's PMS vendor — Sijalucom Console only. */
class PmsVendorDocController
{
    public function __invoke(Request $request): Response
    {
        abort_unless(Auth::guard('vendor')->user()?->hasAccess('support'), 403);
        $hotel = Hotel::query()->find($request->integer('hotel'));
        $name = $hotel?->name ?? 'Hotel';

        return response(PmsGuide::vendorMarkdown($name), 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="integrasi-pms-sangatv-'.Str::slug($name).'.md"',
        ]);
    }
}
