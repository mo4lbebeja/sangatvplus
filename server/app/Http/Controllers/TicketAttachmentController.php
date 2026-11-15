<?php

namespace App\Http\Controllers;

use App\Models\Central\Hotel;
use App\Models\Central\TicketMessage;
use App\Tenancy\HotelManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Ticket attachment: Sijalucom support staff, or staff of the ticket's hotel (internal notes: Sijalucom only). */
class TicketAttachmentController
{
    public function __invoke(TicketMessage $message, int $i): StreamedResponse
    {
        $path = $message->attachments[$i] ?? abort(404);
        $vendor = Auth::guard('vendor')->user();
        $allowed = (bool) $vendor?->hasAccess('support');

        if (! $allowed && ! $message->internal && (int) session('hotel_id') === $message->ticket->hotel_id) {
            $hotel = Hotel::query()->find($message->ticket->hotel_id);
            $allowed = $hotel && app(HotelManager::class)->run($hotel, fn () => Auth::guard('web')->check());
        }
        abort_unless($allowed && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path);
    }
}
