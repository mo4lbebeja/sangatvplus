<?php

namespace App\Vendor;

use App\Models\Central\Hotel;
use App\Models\Central\Ticket;
use App\Models\Central\TicketMessage;
use App\Models\Central\VendorActivity;
use App\Models\Central\VendorUser;
use App\Services\Notifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** Support tickets between hotels (CMS) and Sijalucom (Console). */
class Support
{
    public static function open(Hotel $hotel, string $subject, string $body, string $category, string $priority, string $name, ?string $email, array $attachments = []): Ticket
    {
        $ticket = DB::connection('central')->transaction(function () use ($hotel, $subject, $body, $category, $priority, $name, $email, $attachments) {
            $ticket = Ticket::query()->create([
                'number' => Ticket::nextNumber(), 'hotel_id' => $hotel->id, 'subject' => $subject, 'category' => $category, 'priority' => $priority,
                'status' => 'open', 'requester_name' => $name, 'requester_email' => $email, 'last_reply_at' => now(), 'last_reply_by' => 'hotel',
            ]);
            $ticket->messages()->create(['author_type' => 'hotel', 'author_name' => $name, 'body' => $body, 'attachments' => $attachments ?: null]);

            return $ticket;
        });
        VendorActivity::record('ticket.created', "Tiket {$ticket->number}: {$subject}", $hotel);
        ConsoleNotify::all("Tiket baru {$ticket->number}", "{$hotel->name}: {$subject}", 'heroicon-o-lifebuoy', $priority === 'urgent' ? 'danger' : 'warning', 'support', url('/sijalucom/tiket/'.$ticket->id));
        rescue(fn () => app(Notifier::class)->sendToVendor(($priority === 'urgent' ? '🚨 ' : '🎫 ')."Tiket baru {$ticket->number} · {$hotel->name}",
            "{$subject}\nKategori: ".Ticket::CATEGORIES[$category].' · Prioritas: '.Ticket::PRIORITIES[$priority]."\nDari: {$name}\n\n".mb_substr($body, 0, 600)."\n\n".url('/sijalucom/tiket/'.$ticket->id)), report: false);

        return $ticket;
    }

    public static function replyFromHotel(Ticket $ticket, string $body, string $name, array $attachments = []): TicketMessage
    {
        $msg = $ticket->messages()->create(['author_type' => 'hotel', 'author_name' => $name, 'body' => $body, 'attachments' => $attachments ?: null]);
        $ticket->update(['last_reply_at' => now(), 'last_reply_by' => 'hotel', 'status' => in_array($ticket->status, ['resolved', 'closed', 'waiting'], true) ? 'open' : $ticket->status]);
        rescue(fn () => app(Notifier::class)->sendToVendor("💬 Balasan tiket {$ticket->number} · {$ticket->hotel->name}", mb_substr($body, 0, 600)."\n\n".url('/sijalucom/tiket/'.$ticket->id)), report: false);

        return $msg;
    }

    public static function replyFromVendor(Ticket $ticket, VendorUser $user, string $body, ?string $status = null, bool $internal = false, array $attachments = []): TicketMessage
    {
        $msg = $ticket->messages()->create(['author_type' => 'vendor', 'author_name' => $user->name, 'vendor_user_id' => $user->id, 'body' => $body,
            'internal' => $internal, 'attachments' => $attachments ?: null]);
        $status ??= $internal ? $ticket->status : 'waiting';
        $data = ['status' => $status, 'assigned_to' => $ticket->assigned_to ?? $user->id, 'resolved_at' => in_array($status, ['resolved', 'closed'], true) ? now() : null];
        if (! $internal) {
            $data += ['last_reply_at' => now(), 'last_reply_by' => 'vendor'];
        }
        $ticket->update($data);

        if (! $internal && $ticket->requester_email) {
            try {
                Mail::raw("Halo {$ticket->requester_name},\n\n{$user->name} (Sijalucom) membalas tiket {$ticket->number} \"{$ticket->subject}\":\n\n{$body}\n\nStatus: "
                    .Ticket::STATUSES[$status]."\nBalas lewat CMS SangaTV Plus → Bantuan Sijalucom.\n\n— Tim Sijalucom",
                    fn ($m) => $m->to($ticket->requester_email)->subject("[{$ticket->number}] {$ticket->subject}"));
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $msg;
    }
}
