@php
    $t = $getRecord()->load(['messages', 'hotel', 'assignee']);
    $showInternal = $showInternal ?? true;
    $statusTone = ['open' => 'rose', 'in_progress' => 'sky', 'waiting' => 'amber', 'resolved' => 'emerald', 'closed' => 'slate'][$t->status] ?? 'slate';
@endphp
<div style="display:grid;gap:1rem;grid-template-columns:minmax(0,1fr)">
    <div class="vc-grid">
        <div class="vc-card vc-stat"><span class="vc-stat-icon vc-tone-{{ $statusTone }}"><x-filament::icon icon="heroicon-o-signal" /></span><div><div class="vc-label">Status</div><div class="vc-value">{{ \App\Models\Central\Ticket::STATUSES[$t->status] }}</div></div></div>
        <div class="vc-card vc-stat"><span class="vc-stat-icon vc-tone-amber"><x-filament::icon icon="heroicon-o-flag" /></span><div><div class="vc-label">Prioritas</div><div class="vc-value">{{ \App\Models\Central\Ticket::PRIORITIES[$t->priority] }}</div></div></div>
        <div class="vc-card vc-stat"><span class="vc-stat-icon vc-tone-violet"><x-filament::icon icon="heroicon-o-tag" /></span><div><div class="vc-label">Kategori</div><div class="vc-value">{{ \App\Models\Central\Ticket::CATEGORIES[$t->category] }}</div></div></div>
        <div class="vc-card vc-stat"><span class="vc-stat-icon vc-tone-cyan"><x-filament::icon icon="heroicon-o-user-circle" /></span><div><div class="vc-label">Pelapor</div><div class="vc-value" style="font-size:1rem">{{ $t->requester_name }}</div><div class="vc-muted">{{ $t->requester_email }}</div></div></div>
    </div>
    <div class="vc-card" style="display:grid;gap:1rem">
        @foreach ($t->messages as $m)
            @continue($m->internal && ! $showInternal)
            @php($mine = $m->author_type === 'vendor')
            <div style="display:flex;gap:.75rem;{{ $mine ? 'flex-direction:row-reverse' : '' }}">
                <span class="vc-avatar vc-avatar-sm" style="{{ $mine ? '' : 'background:linear-gradient(135deg,#F59E0B,#F43F5E)' }}">{{ mb_strtoupper(mb_substr($m->author_name, 0, 1)) }}</span>
                <div style="max-width:min(680px,85%);{{ $mine ? 'text-align:right' : '' }}">
                    <div class="vc-muted" style="font-size:.75rem;margin-bottom:.25rem">
                        <strong style="color:inherit">{{ $m->author_name }}</strong> · {{ $mine ? 'Sijalucom' : $t->hotel->name }} · {{ $m->created_at->setTimezone('Asia/Jakarta')->translatedFormat('d M Y H:i') }}
                        @if ($m->internal) · <span style="color:#D97706;font-weight:700">catatan internal</span>@endif
                    </div>
                    <div style="display:inline-block;text-align:left;white-space:pre-line;padding:.75rem 1rem;border-radius:14px;font-size:.88rem;line-height:1.5;
                        {{ $m->internal ? 'background:#FFFBEB;border:1px dashed #F59E0B' : ($mine ? 'background:#4F46E5;color:#fff;border-top-right-radius:4px' : 'background:#F1F5F9;border-top-left-radius:4px') }}">{{ $m->body }}</div>
                    @if ($m->attachments)
                        <div style="margin-top:.35rem;display:flex;gap:.4rem;flex-wrap:wrap;{{ $mine ? 'justify-content:flex-end' : '' }}">
                            @foreach ($m->attachments as $i => $path)
                                <a href="{{ route('ticket.attachment', ['message' => $m->id, 'i' => $i]) }}" target="_blank" class="vc-chip"><x-filament::icon icon="heroicon-o-paper-clip" class="vc-ic" />{{ \Illuminate\Support\Str::limit(basename($path), 28) }}</a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
