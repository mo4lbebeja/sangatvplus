<div class="sg-cmd-history">
    @forelse ($commands as $c)
        <div class="sg-cmd-row">
            <div class="sg-cmd-main">
                <b>{{ $c->type->getLabel() }}</b>
                <small>{{ $c->created_at->timezone(\Filament\Support\Facades\FilamentTimezone::get())->format('d M Y H:i') }}
                    @if ($c->completed_at) · selesai {{ $c->completed_at->diffForHumans($c->created_at, short: true) }} @endif
                </small>
                @if ($c->result['error'] ?? null)
                    <div class="sg-cmd-err">{{ $c->result['error'] }}</div>
                @elseif ($c->status->value === 'pending')
                    <div class="sg-cmd-info">Menunggu TV mengambil perintah (≤1 menit, TV harus menyala & online).</div>
                @elseif ($c->status->value === 'delivered')
                    <div class="sg-cmd-info">Sudah diterima TV, menunggu hasil.</div>
                @endif
            </div>
            <x-filament::badge :color="$c->status->getColor()" :icon="$c->status->getIcon()">{{ $c->status->getLabel() }}</x-filament::badge>
        </div>
    @empty
        <p style="color: var(--sg-muted); font-size: .9rem">Belum ada perintah untuk TV ini.</p>
    @endforelse
</div>
