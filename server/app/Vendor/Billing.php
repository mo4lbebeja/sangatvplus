<?php

namespace App\Vendor;

use App\Models\Central\Hotel;
use App\Models\Central\Invoice;
use App\Models\Central\VendorActivity;
use App\Models\Central\VendorSetting;
use App\Models\Central\VendorUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Monthly rental: a period starts on the activation day (or the day after the current period) and runs whole months,
 * e.g. 16 Nov + 1 month → until 15 Dec (end of day WIB). Invoices extend the rental when paid (or right away).
 */
class Billing
{
    public const TZ = 'Asia/Jakarta';

    public const CYCLES = [1 => '1 bulan', 3 => '3 bulan', 6 => '6 bulan', 12 => '12 bulan'];

    /** @return array{0: Carbon, 1: Carbon} next rental period of $months for this hotel */
    public static function nextPeriod(Hotel $hotel, int $months, ?Carbon $from = null): array
    {
        $today = now(self::TZ)->startOfDay();
        $current = $hotel->service_expires_at?->copy()->setTimezone(self::TZ)->startOfDay();
        $start = $from?->copy()->setTimezone(self::TZ)->startOfDay()
            ?? ($current && $current->gte($today) && $hotel->service_released_at === null ? $current->addDay() : $today);

        return [$start, $start->copy()->addMonthsNoOverflow($months)->subDay()];
    }

    public static function nextNumber(): string
    {
        $prefix = trim((string) VendorSetting::read('invoice_prefix')) ?: 'INV';
        $base = $prefix.'/'.now(self::TZ)->format('Ym').'/';
        $last = Invoice::query()->where('number', 'like', $base.'%')->orderByDesc('number')->value('number');

        return $base.str_pad((string) ((int) substr((string) $last, strlen($base)) + 1), 4, '0', STR_PAD_LEFT);
    }

    /** New invoice for the next $months of rental. $applyNow: extend the rental now (pay later). */
    public static function createInvoice(Hotel $hotel, int $months, bool $applyNow = false, ?Carbon $from = null, ?string $notes = null, array $extras = []): Invoice
    {
        if ($months < 1 || $months > 36) {
            throw new RuntimeException('Durasi sewa 1–36 bulan.');
        }
        $hotel->loadMissing('plan');
        $gross = $hotel->custom_monthly_price ?? $hotel->plan?->monthlyFor($hotel->tv_quota) ?? 0;
        if ($gross <= 0) {
            throw new RuntimeException('Hotel belum punya paket atau harga sewa.');
        }
        [$start, $end] = self::nextPeriod($hotel, $months, $from);

        $label = $hotel->custom_monthly_price !== null ? 'Harga khusus' : 'Paket '.$hotel->plan->name;
        $tvs = $hotel->tv_quota ? " · {$hotel->tv_quota} TV" : '';
        $items = [[
            'description' => "Sewa SangaTV Plus ({$label}{$tvs}) · ".$start->translatedFormat('d M Y').' – '.$end->translatedFormat('d M Y'),
            'qty' => $months, 'unit' => 'bulan', 'price' => $gross, 'amount' => $gross * $months,
        ]];
        $rental = $gross * $months;
        $discount = (int) round($rental * $hotel->discount_percent / 100);
        foreach (self::extraLines($extras) as $line) {
            $items[] = $line;
        }
        $subtotal = array_sum(array_column($items, 'amount'));
        $taxPercent = (float) VendorSetting::read('tax_percent');
        $tax = (int) round(($subtotal - $discount) * $taxPercent / 100);
        $today = now(self::TZ)->startOfDay();
        $due = $start->gt($today) ? $start->copy() : $today->copy()->addDays((int) VendorSetting::read('due_days'));

        return DB::connection('central')->transaction(function () use ($hotel, $months, $applyNow, $notes, $start, $end, $items, $subtotal, $discount, $taxPercent, $tax, $today, $due) {
            $user = Auth::guard('vendor')->user();
            $invoice = Invoice::query()->create([
                'number' => self::nextNumber(), 'hotel_id' => $hotel->id, 'status' => 'unpaid',
                'period_start' => $start->toDateString(), 'period_end' => $end->toDateString(), 'months' => $months,
                'items' => $items, 'subtotal' => $subtotal, 'discount' => $discount, 'tax_percent' => $taxPercent, 'tax' => $tax,
                'total' => $subtotal - $discount + $tax, 'issued_at' => $today->toDateString(), 'due_at' => $due->toDateString(),
                'notes' => $notes, 'created_by' => $user instanceof VendorUser ? $user->id : null,
            ]);
            VendorActivity::record('invoice.created', "Tagihan {$invoice->number} dibuat (".self::rupiah($invoice->total).", {$months} bulan)", $hotel, ['invoice' => $invoice->number]);
            if ($applyNow) {
                self::apply($invoice);
            }

            return $invoice;
        });
    }

    /** @return list<array{description: string, qty: int, unit: string, price: int, amount: int}> */
    private static function extraLines(array $extras): array
    {
        return collect($extras)->filter(fn ($e) => filled($e['description'] ?? null) && (int) ($e['price'] ?? 0) > 0)
            ->map(fn ($e) => ['description' => (string) $e['description'], 'qty' => max(1, (int) ($e['qty'] ?? 1)), 'unit' => (string) ($e['unit'] ?? 'item'),
                'price' => (int) $e['price'], 'amount' => max(1, (int) ($e['qty'] ?? 1)) * (int) $e['price']])->values()->all();
    }

    /** Invoice without rental (installation, technician visit, extra TVs …). Does not change the rental period. */
    public static function createOneOff(Hotel $hotel, array $extras, ?string $notes = null): Invoice
    {
        $items = self::extraLines($extras);
        if ($items === []) {
            throw new RuntimeException('Isi minimal satu biaya.');
        }
        $subtotal = array_sum(array_column($items, 'amount'));
        $taxPercent = (float) VendorSetting::read('tax_percent');
        $tax = (int) round($subtotal * $taxPercent / 100);
        $today = now(self::TZ)->startOfDay();
        $user = Auth::guard('vendor')->user();
        $invoice = Invoice::query()->create([
            'number' => self::nextNumber(), 'hotel_id' => $hotel->id, 'status' => 'unpaid', 'kind' => 'one_off',
            'period_start' => $today->toDateString(), 'period_end' => $today->toDateString(), 'months' => 0,
            'items' => $items, 'subtotal' => $subtotal, 'discount' => 0, 'tax_percent' => $taxPercent, 'tax' => $tax, 'total' => $subtotal + $tax,
            'issued_at' => $today->toDateString(), 'due_at' => $today->copy()->addDays((int) VendorSetting::read('due_days'))->toDateString(),
            'notes' => $notes, 'created_by' => $user instanceof VendorUser ? $user->id : null, 'applied_at' => now(),
        ]);
        VendorActivity::record('invoice.created', "Tagihan biaya {$invoice->number} dibuat (".self::rupiah($invoice->total).')', $hotel, ['invoice' => $invoice->number]);

        return $invoice;
    }

    /**
     * More TVs in the middle of a period: new quota now, prorated difference until the current end date
     * as a one-off invoice (null when nothing is due, e.g. no end date or a lower quota).
     */
    public static function changeQuota(Hotel $hotel, int $quota): ?Invoice
    {
        $hotel->loadMissing('plan');
        $old = $hotel->monthlyPrice();
        $before = $hotel->tv_quota;
        $hotel->update(['tv_quota' => $quota]);
        $new = $hotel->fresh('plan')->monthlyPrice();
        VendorActivity::record('hotel.quota', "Kuota TV {$before} → {$quota}", $hotel);
        $days = $hotel->daysLeft();
        if ($new <= $old || $days === null || $days < 1) {
            return null;
        }
        $amount = (int) round(($new - $old) / 30 * $days);

        return $amount > 0 ? self::createOneOff($hotel, [[
            'description' => "Penambahan kuota TV {$before} → {$quota} (prorata {$days} hari s/d ".$hotel->service_expires_at->setTimezone(self::TZ)->translatedFormat('d M Y').')',
            'qty' => 1, 'unit' => 'paket', 'price' => $amount,
        ]]) : null;
    }

    /** Extend the rental to the invoice's period end (once). */
    public static function apply(Invoice $invoice): void
    {
        if ($invoice->applied_at !== null) {
            return;
        }
        $hotel = $invoice->hotel;
        $current = $hotel->service_expires_at?->copy()->setTimezone(self::TZ)->startOfDay();
        $hotel->service_expires_at = $current && $hotel->service_released_at === null && $current->gt($invoice->period_end) ? $current->toDateString() : $invoice->period_end->toDateString();
        $hotel->activated_at ??= Carbon::parse($invoice->period_start->toDateString(), self::TZ)->utc();
        $hotel->service_released_at = null;
        $hotel->save();
        $invoice->update(['applied_at' => now()]);
        VendorActivity::record('rental.extended', 'Masa sewa sampai '.$hotel->service_expires_at->setTimezone(self::TZ)->translatedFormat('d M Y')." (tagihan {$invoice->number})", $hotel);
    }

    public static function markPaid(Invoice $invoice, string $paidOn, string $method, ?string $ref = null): void
    {
        if (! in_array($invoice->status, ['unpaid', 'draft'], true)) {
            throw new RuntimeException('Tagihan ini sudah lunas atau dibatalkan.');
        }
        $invoice->update([
            'status' => 'paid', 'paid_at' => Carbon::parse($paidOn, self::TZ)->setTimeFrom(now(self::TZ))->utc(),
            'payment_method' => $method, 'payment_ref' => $ref,
        ]);
        VendorActivity::record('invoice.paid', "Tagihan {$invoice->number} lunas (".self::rupiah($invoice->total).')', $invoice->hotel, ['invoice' => $invoice->number, 'method' => $method]);
        if ($invoice->kind !== 'one_off') {
            self::apply($invoice);
        }
        Collections::reactivateIfSettled($invoice->hotel->fresh());
    }

    public static function void(Invoice $invoice, ?string $reason = null): void
    {
        if ($invoice->status === 'paid') {
            throw new RuntimeException('Tagihan lunas tidak bisa dibatalkan.');
        }
        $invoice->update(['status' => 'void', 'notes' => trim(($invoice->notes ? $invoice->notes."\n" : '').'Dibatalkan: '.($reason ?: '-'))]);
        VendorActivity::record('invoice.void', "Tagihan {$invoice->number} dibatalkan", $invoice->hotel, ['reason' => $reason]);
    }

    // ------------------------------------------------------------------ analytics

    /** Monthly recurring revenue of all billable hotels. */
    public static function mrr(?Collection $hotels = null): int
    {
        return (int) ($hotels ?? Hotel::query()->with('plan')->get())->filter->isBillable()->sum(fn (Hotel $h) => $h->monthlyPrice());
    }

    /** @return array<string, int> paid revenue per month "Y-m" for the last $months months (oldest first) */
    public static function revenueByMonth(int $months = 12): array
    {
        $start = now(self::TZ)->startOfMonth()->subMonths($months - 1);
        $out = [];
        for ($m = $start->copy(); $m->lte(now(self::TZ)); $m->addMonth()) {
            $out[$m->format('Y-m')] = 0;
        }
        Invoice::query()->where('status', 'paid')->where('paid_at', '>=', $start->copy()->utc())->get(['paid_at', 'total'])
            ->each(function (Invoice $i) use (&$out) {
                $key = $i->paid_at->setTimezone(self::TZ)->format('Y-m');
                if (isset($out[$key])) {
                    $out[$key] += $i->total;
                }
            });

        return $out;
    }

    /** 1250000 → "satu juta dua ratus lima puluh ribu rupiah" (kwitansi). */
    public static function terbilang(int $n): string
    {
        $words = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];
        $f = function (int $n) use (&$f, $words): string {
            return match (true) {
                $n < 12 => $words[$n],
                $n < 20 => $f($n - 10).' belas',
                $n < 100 => $f(intdiv($n, 10)).' puluh '.$f($n % 10),
                $n < 200 => 'seratus '.$f($n - 100),
                $n < 1000 => $f(intdiv($n, 100)).' ratus '.$f($n % 100),
                $n < 2000 => 'seribu '.$f($n - 1000),
                $n < 1_000_000 => $f(intdiv($n, 1000)).' ribu '.$f($n % 1000),
                $n < 1_000_000_000 => $f(intdiv($n, 1_000_000)).' juta '.$f($n % 1_000_000),
                default => $f(intdiv($n, 1_000_000_000)).' miliar '.$f($n % 1_000_000_000),
            };
        };

        return trim(preg_replace('/\s+/', ' ', $n === 0 ? 'nol' : $f($n))).' rupiah';
    }

    public static function rupiah(int|float|null $amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }
}
