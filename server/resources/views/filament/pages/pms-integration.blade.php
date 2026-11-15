@php($questions = $this->questions())
<x-filament-panels::page>
    <div class="sg-guide">
        {{-- Vendor checklist: show/hide, state remembered per browser --}}
        <section class="sg-card sg-guide-section"
                 x-data="{ open: (() => { try { return localStorage.getItem('sg_pms_checklist') !== 'closed' } catch (e) { return true } })(),
                           toggle() { this.open = !this.open; try { localStorage.setItem('sg_pms_checklist', this.open ? 'open' : 'closed') } catch (e) {} } }">
            <header class="sg-collapse-head" @click="toggle()" role="button" tabindex="0" @keydown.enter.prevent="toggle()" @keydown.space.prevent="toggle()" :aria-expanded="open.toString()">
                <span class="sg-step">?</span>
                <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedClipboardDocumentCheck" class="sg-guide-icon" />
                <div style="flex: 1">
                    <h2>Pertanyaan untuk vendor PMS</h2>
                    <p>Centang yang sudah terjawab dan catat jawabannya. Tersimpan otomatis.</p>
                </div>
                <span class="sg-progress">{{ $this->doneCount() }} / {{ count($questions) }} terjawab</span>
                <span class="sg-collapse-btn" x-text="open ? 'Sembunyikan' : 'Tampilkan'"></span>
            </header>
            <ol x-show="open" x-collapse class="sg-checklist">
                @foreach ($questions as $key => $item)
                    <li wire:key="q-{{ $key }}" @class(['is-done' => $checklist[$key]['done'] ?? false])>
                        <label class="sg-check">
                            <input type="checkbox" wire:model.live="checklist.{{ $key }}.done">
                            <span>
                                <b>{{ $item['q'] }}</b>
                                <small>{{ $item['hint'] }}</small>
                            </span>
                        </label>
                        <input type="text" class="sg-note" placeholder="Jawaban vendor / catatan…" wire:model.live.debounce.800ms="checklist.{{ $key }}.note" maxlength="1000">
                    </li>
                @endforeach
            </ol>
        </section>

        {{-- Quick facts + download --}}
        <div class="sg-card sg-pms-facts">
            <div>
                <span>Base URL API</span>
                <code>{{ $this->baseUrl() }}</code>
            </div>
            <div class="sg-pms-actions">
                <a href="{{ route('pms.vendor-doc') }}" class="sg-chip-link sg-chip-dark">
                    <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedDocumentArrowDown" />
                    Unduh dokumen untuk vendor (.md)
                </a>
                <a href="{{ \App\Filament\Resources\PmsIntegrations\PmsIntegrationResource::getUrl() }}" class="sg-chip-link sg-chip-dark" wire:navigate>
                    <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedKey" />
                    Kelola kunci API
                </a>
                <a href="{{ \App\Filament\Resources\PmsEvents\PmsEventResource::getUrl() }}" class="sg-chip-link sg-chip-dark" wire:navigate>
                    <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedQueueList" />
                    Log kiriman
                </a>
            </div>
        </div>

        {{-- Vendor documentation (same source as the download) --}}
        <article class="sg-card sg-prose">
            {{ $this->docHtml() }}
        </article>
    </div>
</x-filament-panels::page>
