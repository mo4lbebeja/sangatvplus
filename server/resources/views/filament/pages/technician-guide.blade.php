@php($sections = $this->sections())
<x-filament-panels::page>
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
                    @foreach ($this->packages() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="sg-field">
                <span>Launcher bawaan TV</span>
                <select x-model="launcher">
                    @foreach ($this->launchers() as $value => $label)
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
</x-filament-panels::page>
