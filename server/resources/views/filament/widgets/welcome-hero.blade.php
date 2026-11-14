<x-filament-widgets::widget>
    <div class="sg-hero">
        <div style="position: relative; z-index: 1">
            <div class="sg-hero-kicker">{{ mb_strtoupper($hotel) }}</div>
            <h2 class="sg-hero-title">{{ $greeting }}, {{ $name }}</h2>
            <div class="sg-hero-sub">{{ $date }} · {{ $role }}</div>
        </div>
        <nav class="sg-hero-actions" aria-label="Pintasan">
            @foreach ($links as [$label, $url, $path])
                <a href="{{ $url }}" class="sg-chip-link" wire:navigate>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $path }}"/></svg>
                    {{ $label }}
                </a>
            @endforeach
        </nav>
    </div>
</x-filament-widgets::widget>
