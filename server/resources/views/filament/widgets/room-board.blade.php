<x-filament-widgets::widget>
    <div class="sg-card">
        <div class="sg-board-head">
            <div class="sg-board-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.38c0-.62.5-1.12 1.13-1.12h3.75c.62 0 1.12.5 1.12 1.12V21"/></svg>
                Papan kamar
            </div>
            <div class="sg-legend">
                <span><i class="sg-dot" style="background: linear-gradient(135deg,#7fe0ec,#38c6d9)"></i> Terisi</span>
                <span><i class="sg-dot" style="background: var(--sg-border)"></i> Kosong</span>
                <span><i class="sg-dot" style="background:#22c55e"></i> TV menyala</span>
                <span><i class="sg-dot" style="background:#f08a7e"></i> TV mati / standby</span>
                <span><i class="sg-dot" style="box-shadow: inset 0 0 0 1.5px currentColor; opacity:.5"></i> Belum ada TV</span>
            </div>
        </div>
        <div class="sg-board-body">
            @forelse ($floors as $floor => $rooms)
                <div class="sg-floor">
                    <div class="sg-floor-label">Lantai {{ $floor }}</div>
                    <div class="sg-rooms">
                        @foreach ($rooms as $room)
                            <a href="/admin/rooms?tableSearch={{ urlencode($room['number']) }}" wire:navigate
                               class="sg-room {{ $room['guest'] ? 'is-occupied' : '' }} {{ $room['active'] ? '' : 'is-inactive' }}"
                               title="Kamar {{ $room['number'] }}{{ $room['guest'] ? ' · '.$room['guest'] : ' · kosong' }} · TV {{ ['on' => 'menyala', 'off' => 'mati / standby', 'none' => 'belum terdaftar'][$room['tv']] }}">
                                <span class="sg-tv {{ $room['tv'] }}"></span>
                                {{ $room['number'] }}
                                @if ($room['guest'])
                                    <small>{{ \Illuminate\Support\Str::of($room['guest'])->afterLast(' ') }}</small>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @empty
                <p style="color: var(--sg-muted); font-size: .85rem">Belum ada kamar.</p>
            @endforelse
        </div>
    </div>
</x-filament-widgets::widget>
