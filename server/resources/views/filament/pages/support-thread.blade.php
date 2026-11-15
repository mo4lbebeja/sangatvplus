<div style="display:grid;gap:.9rem;max-height:55vh;overflow:auto;padding:.25rem">
    @foreach ($ticket->messages->where('internal', false) as $m)
        @php($sjl = $m->author_type === 'vendor')
        <div style="display:flex;{{ $sjl ? '' : 'justify-content:flex-end' }}">
            <div style="max-width:85%">
                <div style="font-size:.74rem;color:#64748B;margin-bottom:.2rem;{{ $sjl ? '' : 'text-align:right' }}"><strong>{{ $m->author_name }}</strong>{{ $sjl ? ' · Sijalucom' : '' }} · {{ $m->created_at->setTimezone('Asia/Jakarta')->translatedFormat('d M H:i') }}</div>
                <div style="white-space:pre-line;padding:.7rem .95rem;border-radius:14px;font-size:.88rem;{{ $sjl ? 'background:#EEF2FF;color:#1E1B4B' : 'background:#0E7490;color:#fff' }}">{{ $m->body }}</div>
                @foreach ($m->attachments ?? [] as $i => $path)
                    <a href="{{ route('ticket.attachment', ['message' => $m->id, 'i' => $i]) }}" target="_blank" style="font-size:.75rem;color:#4F46E5;margin-right:.5rem">📎 {{ \Illuminate\Support\Str::limit(basename($path), 28) }}</a>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
