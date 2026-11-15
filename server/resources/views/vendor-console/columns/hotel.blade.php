@php($h = $getRecord())
<div style="display:flex;align-items:center;gap:.75rem;padding:.35rem 0">
    <span class="vc-avatar">{{ \Illuminate\Support\Str::of($h->name)->explode(' ')->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('') }}</span>
    <div style="line-height:1.25">
        <div style="font-weight:700" class="text-gray-950 dark:text-white">{{ $h->name }}</div>
        <div class="vc-muted"><code>{{ $h->slug }}</code>{{ $h->city ? ' · '.$h->city : '' }}{{ $h->contact_name ? ' · '.$h->contact_name : '' }}</div>
    </div>
</div>
