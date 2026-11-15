@php
    $hotels = \App\Models\Central\Hotel::query()->with('plan')->get();
    $mrr = \App\Vendor\Billing::mrr($hotels);
    $overdue = \App\Models\Central\Invoice::query()->overdue()->count();
    $user = \Filament\Facades\Filament::auth()->user();
@endphp
<div class="vc-side-foot">
    @if ($user?->hasAccess('finance'))
        <div class="vc-side-card">
            <div class="vc-side-card-row"><x-filament::icon icon="heroicon-o-arrow-trending-up" class="vc-ic" /><span>MRR</span><strong>{{ \App\Vendor\Billing::rupiah($mrr) }}</strong></div>
            <div class="vc-side-card-row"><x-filament::icon icon="heroicon-o-building-office-2" class="vc-ic" /><span>Hotel berbayar</span><strong>{{ $hotels->filter->isBillable()->count() }}</strong></div>
            <div class="vc-side-card-row {{ $overdue ? 'is-danger' : '' }}"><x-filament::icon icon="heroicon-o-exclamation-triangle" class="vc-ic" /><span>Lewat tempo</span><strong>{{ $overdue }}</strong></div>
        </div>
    @endif
    <div class="vc-side-user">
        <span class="vc-avatar vc-avatar-sm">{{ mb_substr($user?->name ?? 'S', 0, 1) }}</span>
        <span class="vc-side-user-txt"><strong>{{ $user?->name }}</strong><small>{{ str(\App\Models\Central\VendorUser::ROLES[$user?->role] ?? '')->before(' (') }}</small></span>
    </div>
</div>
