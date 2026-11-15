<x-filament-widgets::widget>
    <div class="vc-card" style="height:100%">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem">
            <div style="display:flex;align-items:center;gap:.5rem;font-weight:700"><x-filament::icon icon="heroicon-o-bolt" class="vc-ic" />Aktivitas terbaru</div>
            @if (\App\Vendor\Resources\Activities\ActivityResource::canViewAny())
                <a href="{{ \App\Vendor\Resources\Activities\ActivityResource::getUrl() }}" class="vc-muted" style="font-size:.78rem" wire:navigate>Semua →</a>
            @endif
        </div>
        <div class="vc-feed">
            @forelse ($items as $a)
                @php([$icon, $tone] = \App\Vendor\Widgets\RecentActivity::ICONS[$a->action] ?? ['heroicon-o-sparkles', 'slate'])
                <div class="vc-feed-item">
                    <span class="vc-feed-icon vc-tone-{{ $tone }}"><x-filament::icon :icon="$icon" /></span>
                    <div class="vc-feed-txt">
                        @if ($a->hotel)<strong>{{ $a->hotel->name }}</strong> · @endif{{ $a->description }}
                        <small>{{ $a->created_at->diffForHumans() }} · {{ $a->user?->name ?? 'Sistem' }}</small>
                    </div>
                </div>
            @empty
                <div class="vc-empty"><x-filament::icon icon="heroicon-o-inbox" />Belum ada aktivitas.</div>
            @endforelse
        </div>
    </div>
</x-filament-widgets::widget>
