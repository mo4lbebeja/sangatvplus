@php($sections = $this->sections())
@php($questions = \App\Vendor\Guides\PmsGuide::questions())
<x-filament-panels::page>
    <link rel="stylesheet" href="{{ asset('css/sangatv-guide.css') }}?v={{ @filemtime(public_path('css/sangatv-guide.css')) }}">
    <x-filament::tabs contained>
        <x-filament::tabs.item icon="heroicon-o-wrench-screwdriver" :active="$tab === 'teknisi'" wire:click="$set('tab', 'teknisi')">Panduan Teknisi (ADB)</x-filament::tabs.item>
        <x-filament::tabs.item icon="heroicon-o-arrows-right-left" :active="$tab === 'pms'" wire:click="$set('tab', 'pms')">Integrasi PMS · Panduan & Checklist</x-filament::tabs.item>
    </x-filament::tabs>

    @if ($tab === 'teknisi')
        <div wire:key="tab-teknisi">

    <div
        class="sg-guide"
        x-data="{
            ip: @js($this->ip) || (() => { try { return localStorage.getItem('sg_tv_ip') || '' } catch (e) { return '' } })(),
            pkg: (() => { try { return localStorage.getItem('sg_tv_pkg') || 'com.sangatv.plus' } catch (e) { return 'com.sangatv.plus' } })(),
            launcher: 'com.google.android.tvlauncher',
            copied: null,
            remember() { try { localStorage.setItem('sg_tv_ip', this.ip); localStorage.setItem('sg_tv_pkg', this.pkg) } catch (e) {} },
            fill(t) {
                const ip = this.ip.trim() || '<IP-TV>';
                return t.replaceAll('{s}', '-s ' + ip + ':5555').replaceAll('{ip}', ip).replaceAll('{pkg}', this.pkg).replaceAll('{launcher}', this.launcher);
            },
            copy(t, id) {
                const text = this.fill(t);
                const done = () => { this.copied = id; setTimeout(() => { if (this.copied === id) this.copied = null }, 1500) };
                if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(text).then(done); return }
                const ta = document.createElement('textarea'); ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
                document.body.appendChild(ta); ta.select(); try { document.execCommand('copy'); done() } finally { ta.remove() }
            },
        }"
    >
        {{-- Parameters --}}
        <div class="sg-card sg-guide-params">
            <label class="sg-field">
                <span>IP TV</span>
                <input type="text" inputmode="decimal" placeholder="mis. 192.168.101.4" x-model="ip" @input.debounce.400ms="remember()" autocomplete="off" spellcheck="false">
            </label>
            <label class="sg-field">
                <span>Paket aplikasi</span>
                <select x-model="pkg" @change="remember()">
                    @foreach (\App\Vendor\Guides\TechnicianSteps::packages() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="sg-field">
                <span>Launcher bawaan TV</span>
                <select x-model="launcher">
                    @foreach (\App\Vendor\Guides\TechnicianSteps::launchers() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        {{-- Table of contents --}}
        <nav class="sg-guide-toc" aria-label="Daftar langkah">
            @foreach ($sections as $i => $section)
                <a href="#{{ $section['id'] }}"><b>{{ $i + 1 }}</b> {{ $section['title'] }}</a>
            @endforeach
        </nav>

        @foreach ($sections as $i => $section)
            <section id="{{ $section['id'] }}" class="sg-card sg-guide-section">
                <header>
                    <span class="sg-step">{{ $i + 1 }}</span>
                    <x-filament::icon :icon="$section['icon']" class="sg-guide-icon" />
                    <div>
                        <h2>{{ $section['title'] }}</h2>
                        <p>{{ $section['intro'] }}</p>
                    </div>
                </header>
                <ol>
                    @foreach ($section['items'] as $j => $item)
                        @php($id = $section['id'].'-'.$j)
                        <li @class(['is-warn' => $item['warn'] ?? false])>
                            <div class="sg-guide-label">{{ $item['label'] }}</div>
                            @isset($item['cmd'])
                                <div class="sg-cmd">
                                    <code x-text="fill(@js($item['cmd']))">{{ $item['cmd'] }}</code>
                                    <button type="button" @click="copy(@js($item['cmd']), @js($id))" :aria-label="copied === @js($id) ? 'Tersalin' : 'Salin perintah'" :title="copied === @js($id) ? 'Tersalin' : 'Salin'">
                                        <x-filament::icon x-show="copied !== {{ Js::from($id) }}" :icon="\Filament\Support\Icons\Heroicon::OutlinedClipboardDocument" />
                                        <x-filament::icon x-show="copied === {{ Js::from($id) }}" x-cloak :icon="\Filament\Support\Icons\Heroicon::OutlinedCheck" />
                                    </button>
                                </div>
                            @endisset
                        </li>
                    @endforeach
                </ol>
            </section>
        @endforeach
    </div>

        </div>
    @else
        <div wire:key="tab-pms" style="display:grid;gap:1rem">
            <div class="vc-card" style="display:flex;flex-wrap:wrap;gap:1rem;align-items:center">
                <span class="vc-stat-icon vc-tone-indigo"><x-filament::icon icon="heroicon-o-building-office-2" /></span>
                <div style="flex:1;min-width:14rem"><div class="vc-label">Hotel</div><div class="vc-muted">Checklist dan dokumen vendor disimpan per hotel.</div></div>
                <x-filament::input.wrapper style="min-width:16rem">
                    <x-filament::input.select wire:model.live="hotelId">
                        @foreach ($this->hotels() as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>

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
                <code>{{ \App\Vendor\Guides\PmsGuide::baseUrl() }}</code>
            </div>
            <div class="sg-pms-actions">
                <a href="{{ route('vendor.pms-doc', ['hotel' => $hotelId]) }}" class="sg-chip-link sg-chip-dark">
                    <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedDocumentArrowDown" />
                    Unduh dokumen untuk vendor (.md)
                </a>
            </div>
        </div>

        {{-- Vendor documentation (same source as the download) --}}
        <article class="sg-card sg-prose">
            {{ $this->docHtml() }}
        </article>
    </div>

        </div>
    @endif
</x-filament-panels::page>
