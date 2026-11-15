<?php

namespace App\Vendor;

use App\Models\Central\Hotel;
use App\Models\Central\VendorActivity;
use App\Tenancy\HotelManager;
use Throwable;

/** New client hotel: database + admin, subscription fields, starter content, activation (paid / invoice / trial / later). */
class Onboarding
{
    public const FIELDS = ['city', 'contact_name', 'contact_phone', 'contact_email', 'plan_id', 'tv_quota', 'billing_months',
        'custom_monthly_price', 'discount_percent', 'auto_invoice', 'billing_name', 'billing_email', 'npwp', 'billing_address', 'source'];

    /** @return array{hotel: Hotel, password: string, note: string} */
    public static function create(array $data): array
    {
        $tenancy = app(HotelManager::class);
        $r = $tenancy->provision($data['slug'], $data['name'], $data['admin_email']);
        $hotel = $r['hotel'];
        $hotel->update(collect($data)->only(self::FIELDS)->map(fn ($v) => $v === '' ? null : $v)->all()
            + ['discount_percent' => (float) ($data['discount_percent'] ?? 0), 'billing_months' => (int) ($data['billing_months'] ?? 1)]);
        VendorActivity::record('hotel.created', "Hotel dibuat (kode {$hotel->slug})", $hotel);

        $note = '';
        if (filled($data['template_hotel_id'] ?? null) && ($source = Hotel::query()->find($data['template_hotel_id']))) {
            try {
                $tenancy->copyContent($source, $hotel, (array) ($data['template_parts'] ?? array_keys(HotelManager::CONTENT_PARTS)));
                $hotel->update(['content_template' => ['from' => $source->slug, 'parts' => $data['template_parts'] ?? []]]);
                $note .= " Konten disalin dari {$source->name}.";
            } catch (Throwable $e) {
                report($e);
                $note .= ' Salin konten gagal: '.$e->getMessage();
            }
        }

        try {
            switch ($data['activation'] ?? 'later') {
                case 'paid':
                case 'unpaid':
                    $invoice = Billing::createInvoice($hotel->fresh(), (int) ($data['months'] ?? 1), applyNow: true);
                    if ($data['activation'] === 'paid') {
                        Billing::markPaid($invoice, now(Billing::TZ)->toDateString(), $data['method'] ?? 'transfer');
                    }
                    $note .= " Tagihan {$invoice->number} dibuat.";
                    break;
                case 'trial':
                    $days = (int) (($data['trial_days'] ?? null) ?: 14);
                    $end = now(Billing::TZ)->addDays($days - 1);
                    $hotel->update(['service_expires_at' => $end->toDateString(), 'activated_at' => now()]);
                    VendorActivity::record('rental.trial', "Uji coba {$days} hari sampai ".$end->translatedFormat('d M Y'), $hotel);
                    $note .= " Uji coba sampai {$end->translatedFormat('d M Y')}.";
                    break;
            }
        } catch (Throwable $e) {
            $note .= ' Aktivasi belum berhasil: '.$e->getMessage();
        }

        return ['hotel' => $hotel->fresh(), 'password' => $r['password'], 'note' => trim($note)];
    }
}
