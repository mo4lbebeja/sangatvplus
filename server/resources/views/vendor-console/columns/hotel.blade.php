@php($h = $getRecord())
<div style="display:flex;align-items:center;gap:.8rem;padding:.4rem 0">
    <span class="vc-avatar">{{ \Illuminate\Support\Str::of($h->name)->explode(' ')->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') }}</span>
    <div style="line-height:1.25;min-width:0">
        <div style="font-weight:700;display:flex;gap:.45rem;align-items:center" class="text-gray-950 dark:text-white">{{ $h->name }} <span class="vc-code">{{ $h->slug }}</span></div>
        <div class="vc-meta">
            @if ($h->city)<span><x-filament::icon icon="heroicon-o-map-pin" />{{ $h->city }}</span>@endif
            @if ($h->contact_name)<span><x-filament::icon icon="heroicon-o-user" />{{ $h->contact_name }}</span>@endif
            @if ($h->contact_phone)<span><x-filament::icon icon="heroicon-o-phone" />{{ $h->contact_phone }}</span>@endif
        </div>
    </div>
</div>
