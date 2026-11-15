@php($d = \App\Vendor\HotelStats::details($getRecord()))
@if (! $d['ok'])
    <p class="text-danger-600 text-sm">Database hotel tidak bisa dibaca: {{ $d['error'] ?? '' }}</p>
@elseif ($d['devices'] === [])
    <p class="vc-muted">Belum ada TV terdaftar.</p>
@else
    <div style="overflow-x:auto">
        <table class="vc-table">
            <thead><tr><th>Kamar</th><th>Status</th><th>Model</th><th>Versi</th><th>Terakhir terhubung</th><th>Catatan</th></tr></thead>
            <tbody>
            @foreach ($d['devices'] as $tv)
                <tr>
                    <td><strong>{{ $tv['room'] }}</strong></td>
                    <td><span class="vc-dot {{ $tv['state'] }}"></span>{{ ['on' => 'Menyala', 'standby' => 'Standby', 'off' => 'Offline'][$tv['state']] }}</td>
                    <td>{{ $tv['model'] ?? '–' }}</td>
                    <td>{{ $tv['version'] ? 'v'.$tv['version'] : '–' }}</td>
                    <td class="vc-muted">{{ $tv['last_seen'] ? \Illuminate\Support\Carbon::parse($tv['last_seen'])->diffForHumans() : 'belum pernah' }}</td>
                    <td class="vc-muted">{{ collect([$tv['owner'] ? 'Device Owner' : null, $tv['held'] ? 'Update ditahan' : null])->filter()->implode(' · ') ?: '–' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif
