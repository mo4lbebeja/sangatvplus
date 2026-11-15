@php
    $u = \Filament\Facades\Filament::auth()->user();
    $actions = array_filter([
        $u?->hasAccess('owner') ? ['Hotel baru', 'Daftarkan klien & aktifkan sewa', 'heroicon-o-building-office-2', 'indigo', \App\Vendor\Resources\Hotels\HotelResource::getUrl('index').'?action=create'] : null,
        $u?->hasAccess('finance') ? ['Buat tagihan', 'Tagihan sewa untuk satu hotel', 'heroicon-o-document-plus', 'emerald', \App\Vendor\Resources\Invoices\InvoiceResource::getUrl('index')] : null,
        $u?->hasAccess('support') ? ['Tiket dukungan', 'Keluhan & permintaan hotel', 'heroicon-o-lifebuoy', 'rose', \App\Vendor\Resources\Tickets\TicketResource::getUrl('index')] : null,
        $u?->hasAccess('support') ? ['Pengumuman', 'Kirim info ke CMS semua hotel', 'heroicon-o-megaphone', 'amber', \App\Vendor\Resources\Notices\NoticeResource::getUrl('index')] : null,
        $u?->hasAccess('finance') ? ['Laporan bisnis', 'Pendapatan, piutang, penggunaan', 'heroicon-o-presentation-chart-line', 'sky', \App\Vendor\Pages\Reports::getUrl()] : null,
        $u?->hasAccess('owner') ? ['Pengaturan', 'Kop, rekening, PPN, jatuh tempo', 'heroicon-o-cog-6-tooth', 'slate', \App\Vendor\Pages\Settings::getUrl()] : null,
    ]);
@endphp
<x-filament-widgets::widget>
    <div class="vc-actions-grid">
        @foreach ($actions as [$title, $desc, $icon, $tone, $url])
            <a href="{{ $url }}" class="vc-action" wire:navigate>
                <span class="vc-stat-icon vc-tone-{{ $tone }}"><x-filament::icon :icon="$icon" /></span>
                <div><strong>{{ $title }}</strong><small>{{ $desc }}</small></div>
            </a>
        @endforeach
    </div>
</x-filament-widgets::widget>
