@php($b = fn ($v) => \App\Vendor\Pages\ServerMonitor::bytes($v))
@php($ago = fn ($t) => $t ? \Illuminate\Support\Carbon::createFromTimestamp($t)->diffForHumans() : 'belum ada')
<x-filament-panels::page>
    <div class="vc-grid">
        <div class="vc-card vc-stat"><span class="vc-stat-icon vc-tone-{{ $disk['pct'] > 85 ? 'rose' : 'indigo' }}"><x-filament::icon icon="heroicon-o-circle-stack" /></span>
            <div style="flex:1"><div class="vc-label">Disk</div><div class="vc-value">{{ $disk['pct'] }}%</div><div class="vc-muted">{{ $b($disk['used']) }} dari {{ $b($disk['total']) }}</div>
            <div @class(['vc-progress', 'is-danger' => $disk['pct'] > 85, 'is-warn' => $disk['pct'] > 70 && $disk['pct'] <= 85])><i style="width: {{ $disk['pct'] }}%"></i></div></div></div>
        <div class="vc-card vc-stat"><span class="vc-stat-icon vc-tone-cyan"><x-filament::icon icon="heroicon-o-cpu-chip" /></span>
            <div style="flex:1"><div class="vc-label">Memori</div><div class="vc-value">{{ $mem ? $mem['pct'].'%' : '–' }}</div><div class="vc-muted">{{ $mem ? $b($mem['used']).' dari '.$b($mem['total']) : 'tidak tersedia' }}</div>
            @if ($mem)<div @class(['vc-progress', 'is-danger' => $mem['pct'] > 90, 'is-warn' => $mem['pct'] > 75 && $mem['pct'] <= 90])><i style="width: {{ $mem['pct'] }}%"></i></div>@endif</div></div>
        <div class="vc-card vc-stat"><span class="vc-stat-icon vc-tone-amber"><x-filament::icon icon="heroicon-o-bolt" /></span>
            <div><div class="vc-label">Beban CPU (1/5/15 mnt)</div><div class="vc-value">{{ collect($load)->map(fn ($l) => number_format($l, 2, ',', '.'))->implode(' · ') }}</div><div class="vc-muted">{{ $cpus }} core{{ $uptime ? ' · '.$uptime : '' }}</div></div></div>
        <div class="vc-card vc-stat"><span class="vc-stat-icon vc-tone-violet"><x-filament::icon icon="heroicon-o-command-line" /></span>
            <div><div class="vc-label">Platform</div><div class="vc-value" style="font-size:.95rem">PHP {{ $php }} · Laravel {{ $laravel }}</div><div class="vc-muted">mode {{ $env }} · log {{ $b($logs) }}</div></div></div>
    </div>

    <div class="vc-grid">
        @foreach ([['Penjadwal (snapshot 15 mnt)', $snapshot ? $snapshot->diffForHumans() : 'belum berjalan', $snapshot && $snapshot->gt(now()->subMinutes(40)), 'heroicon-o-clock'],
                   ['Backup database pusat', $ago($central_backup), $central_backup && $central_backup > now()->subHours(36)->getTimestamp(), 'heroicon-o-server'],
                   ['Salinan Google Drive', $drive ? 'aktif' : 'tidak diatur', $drive, 'heroicon-o-cloud-arrow-up'],
                   ['Telegram & email peringatan', ($telegram ? 'Telegram ✓' : 'Telegram ✗').' · '.($mail ? 'Email ✓' : 'Email ✗'), $telegram && $mail, 'heroicon-o-bell-alert']] as [$label, $val, $ok, $icon])
            <div class="vc-card vc-stat"><span class="vc-stat-icon vc-tone-{{ $ok ? 'emerald' : 'rose' }}"><x-filament::icon :icon="$icon" /></span>
                <div><div class="vc-label">{{ $label }}</div><div class="vc-value" style="font-size:.95rem">{{ $val }}</div></div></div>
        @endforeach
    </div>

    <div class="vc-card">
        <div class="vc-label" style="margin-bottom:.4rem">Per hotel</div>
        <div style="overflow-x:auto">
        <table class="vc-table">
            <thead><tr><th>Hotel</th><th>Database</th><th style="text-align:right">Ukuran DB</th><th style="text-align:right">Media</th><th>Backup terakhir</th></tr></thead>
            <tbody>
            @foreach ($hotels as $row)
                <tr>
                    <td><strong>{{ $row['hotel']->name }}</strong> <span class="vc-code">{{ $row['hotel']->slug }}</span></td>
                    <td class="vc-muted">{{ $row['hotel']->database }}</td>
                    <td style="text-align:right">{{ $b($row['db']) }}</td>
                    <td style="text-align:right">{{ $b($row['media']) }}</td>
                    <td @class(['text-danger-600' => ! $row['backup'] || $row['backup'] < now()->subHours(36)->getTimestamp()])>{{ $ago($row['backup']) }}</td>
                </tr>
            @endforeach
            <tr><td><strong>Database pusat</strong></td><td class="vc-muted">{{ config('database.connections.central.database') }}</td><td style="text-align:right">{{ $b($central) }}</td><td></td><td>{{ $ago($central_backup) }}</td></tr>
            </tbody>
        </table>
        </div>
    </div>
</x-filament-panels::page>
