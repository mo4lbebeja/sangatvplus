@php
    $menu = [
        ['TV', 'Siaran langsung dan jadwal acara', 'heroicon-o-tv'],
        ['Info Hotel', 'Tentang hotel, Wi-Fi, dan kontak', 'heroicon-o-information-circle'],
        ['Room Service', 'Menu makanan dan minuman ke kamar', 'heroicon-o-cake'],
        ['Fasilitas', 'Kolam, kebugaran, dan ruang pertemuan', 'heroicon-o-sparkles'],
        ['Galeri', 'Suasana hotel dalam foto', 'heroicon-o-photo'],
        ['Aplikasi', 'YouTube, Netflix, dan aplikasi lainnya', 'heroicon-o-squares-2x2'],
    ];
    $bg = $photo ? "background-image:url('".e($photo)."')" : '';
@endphp
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
<style>
.tvt{display:grid;grid-template-columns:minmax(0,1.75fr) minmax(250px,1fr);gap:1rem}
@media(max-width:900px){.tvt{grid-template-columns:1fr}}
.tvt-screen{position:relative;aspect-ratio:16/9;container-type:inline-size;overflow:hidden;border-radius:.6rem;background:#000;box-shadow:0 0 0 .4rem #15181c,0 14px 30px -14px rgba(0,0,0,.6);outline:none;font-family:'Plus Jakarta Sans',sans-serif;color:#fff}
.tvt-screen:focus-visible{box-shadow:0 0 0 .4rem #15181c,0 0 0 .6rem #38c6d9}
.tvt-l{position:absolute;inset:0}
.tvt-photo{position:absolute;inset:0;background:linear-gradient(135deg,#27536b,#b98d5c);background-size:cover;background-position:center}
.tvt-serif{font-family:'Playfair Display',Georgia,serif}.tvt-tech{font-family:'Michroma',sans-serif}
.tvt-i{cursor:pointer;transition:all .25s ease}.tvt-i svg{width:1em;height:1em;flex:none}
.tvt-ctl{display:flex;align-items:center;gap:.5rem;margin-top:.8rem;font-size:.78rem;color:#6b7280}
.tvt-ctl button{display:grid;place-items:center;width:1.9rem;height:1.9rem;border-radius:.45rem;border:1px solid rgba(120,130,140,.4)}
.tvt-list{display:grid;gap:.45rem;align-content:start}
.tvt-card{display:flex;gap:.65rem;align-items:center;text-align:left;width:100%;padding:.5rem .6rem;border-radius:.6rem;border:1.5px solid rgba(120,130,140,.25);transition:all .15s}
.tvt-card:hover{border-color:rgba(56,198,217,.6)}.tvt-card.on{border-color:#0e9fb3;background:rgba(56,198,217,.1)}
.tvt-sw{display:flex;flex:none;border-radius:.4rem;overflow:hidden}.tvt-sw i{width:.8rem;height:2.1rem;display:block}
.tvt-card b{display:block;font-size:.85rem;line-height:1.2}.tvt-card small{display:block;font-size:.7rem;line-height:1.25;opacity:.72}
/* Royal */
.r-rail{position:absolute;inset:0 auto 0 0;width:31%;background:#0b0906;padding:3.5cqw 3cqw;z-index:2}
.r-item{display:flex;align-items:center;gap:1cqw;height:4.6cqw;padding-left:1.2cqw;border-left:.2cqw solid transparent;font-size:1.65cqw;color:#e2d6be}.r-item.on{padding-left:2.2cqw;border-color:#d9b56a;color:#d9b56a;font-size:1.95cqw}
.r-frame{position:absolute;inset:1.5cqw 1.5cqw 1.5cqw 32.5%;border:1px solid rgba(217,181,106,.75);z-index:2}
/* Tropical */
.t-leaf{height:12.4cqw;width:12cqw;padding:1.5cqw;border-radius:3.6cqw .8cqw 3.6cqw .8cqw;background:rgba(19,53,42,.93);display:flex;flex-direction:column;justify-content:space-between;font-size:1.3cqw;font-weight:700;overflow:hidden;white-space:nowrap}.t-leaf svg{font-size:2.8cqw;color:#8fd694}
.t-leaf.on{width:27cqw;background:#8fd694;color:#08261a;font-size:1.9cqw}.t-leaf.on svg{color:#08261a}
/* Sunset */
.s-w{position:absolute;left:50%;top:0;width:7.8cqw;height:7.8cqw;margin-left:-3.9cqw;border-radius:50%;display:grid;place-items:center;font-size:3.5cqw;border:1px solid rgba(242,166,90,.7);background:#3b201b;color:#f2a65a;transition:transform .34s ease,opacity .34s ease,background .2s}.s-w.on{background:#f2a65a;color:#2b1405}
/* Heritage */
.h-gate{clip-path:polygon(0 100%,0 15%,2.5% 15%,2.5% 10%,5% 10%,5% 5%,7.5% 5%,7.5% 0,92.5% 0,92.5% 5%,95% 5%,95% 10%,97.5% 10%,97.5% 15%,100% 15%,100% 100%);background:#e0a93f}
.h-tile{flex:1;height:14.6cqw;padding-top:3.2cqw;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.8cqw;font-size:1.55cqw;background:#262a63;color:#f6eee2;clip-path:polygon(0 100%,0 21%,7% 21%,7% 14%,14% 14%,14% 7%,21% 7%,21% 0,79% 0,79% 7%,86% 7%,86% 14%,93% 14%,93% 21%,100% 21%,100% 100%)}.h-tile svg{font-size:3.3cqw;color:#e0a93f}
.h-tile.on{background:#e0a93f;color:#241407;transform:translateY(-1.2cqw)}.h-tile.on svg{color:#241407}
/* Daerah */
.tvt-i svg.s-ang{display:block;width:72%;height:auto;margin:0 auto .4cqw;color:#c39a5f;transform-origin:50% 100%}.tvt-i svg.s-ang.on{color:#e2b04a;animation:tvt-shake .26s linear infinite}
@keyframes tvt-shake{0%,100%{transform:rotate(-3.5deg)}50%{transform:rotate(3.5deg)}}
.s-base{display:flex;align-items:center;justify-content:center;gap:.5cqw;height:3.4cqw;border-radius:.6cqw;background:rgba(26,16,9,.86);border:1px solid rgba(226,176,74,.55);font-size:1cqw;font-weight:800;white-space:nowrap}.s-base svg{font-size:1.5cqw;color:#e2b04a}.s-base.on{background:#e2b04a;color:#2a1705}.s-base.on svg{color:#2a1705}
@media (prefers-reduced-motion:reduce){.tvt-i svg.s-ang.on{animation:none}}
</style>
<div x-data="{
        theme: $wire.$entangle('{{ $getStatePath() }}'), active: 0, wheel: 0, n: 6,
        time: new Date().toLocaleTimeString('id-ID', {hour:'2-digit', minute:'2-digit'}).replace(':','.'),
        menu: @js(array_map(fn ($m) => ['label' => $m[0], 'desc' => $m[1]], $menu)),
        move(d) { this.wheel += d; this.active = ((this.active + d) % this.n + this.n) % this.n },
        pick(i) { this.wheel += ((i - this.active + 9) % 6) - 3; this.active = i },
        d(i) { let v = ((i - this.wheel + 3) % 6 + 6) % 6 - 3; return v },
     }" class="tvt">
    <div>
        <div class="tvt-screen" tabindex="0" role="application" aria-label="Pratinjau layar TV, gunakan tombol panah"
             @keydown.left.prevent="move(-1)" @keydown.right.prevent="move(1)" @keydown.up.prevent="move(-1)" @keydown.down.prevent="move(1)">

            {{-- Samudra --}}
            <div class="tvt-l" x-show="theme === 'ocean'" x-cloak>
                <div class="tvt-photo" style="{{ $bg }}"></div>
                <div class="tvt-l" style="background:linear-gradient(90deg,rgba(3,18,32,.88),rgba(3,18,32,.1) 75%)"></div>
                <div class="tvt-tech" style="position:absolute;left:5%;top:7%;font-size:1.7cqw;letter-spacing:.18em">{{ str($hotelName)->upper() }}</div>
                <div style="position:absolute;right:5%;top:6%;font-size:4cqw;font-weight:300" x-text="time"></div>
                <div class="tvt-tech" style="position:absolute;left:5%;bottom:24%;font-size:3cqw">SELAMAT DATANG</div>
                <div style="position:absolute;inset:auto 0 0;height:14%;background:#f2f8fb;display:flex;align-items:center;padding-left:14%;color:#0b2c47">
                    @foreach ($menu as $i => $m)
                        <div class="tvt-i" @mouseenter="pick({{ $i }})" :style="active === {{ $i }} ? {background:'#0b3d66', color:'#fff'} : {background:'transparent', color:'#0b2c47'}" style="height:100%;width:11%;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.4cqw;font-size:.95cqw;font-weight:600;transform:skewX(-9deg)"><x-filament::icon :icon="$m[2]" style="font-size:2.2cqw;width:2.2cqw;height:2.2cqw" />{{ $m[0] }}</div>
                    @endforeach
                </div>
            </div>

            {{-- Emas Klasik --}}
            <div class="tvt-l" x-show="theme === 'royal'" x-cloak>
                <div class="tvt-photo" style="left:31%;{{ $bg }}"></div>
                <div class="tvt-l" style="left:31%;background:linear-gradient(180deg,transparent 45%,rgba(11,9,6,.93)),linear-gradient(90deg,#0b0906,transparent 42%)"></div>
                <div class="r-frame"></div>
                <div class="r-rail">
                    <div class="tvt-serif" style="color:#d9b56a;font-size:1.7cqw">{{ $hotelName }}</div>
                    <div style="width:4cqw;height:2px;background:#d9b56a;margin:1.3cqw 0"></div>
                    <div style="font-size:1cqw;color:#b3a384">Selamat datang</div><div class="tvt-serif" style="font-size:1.8cqw;margin-bottom:1.6cqw">Bapak Tamu</div>
                    @foreach ($menu as $i => $m)
                        <div class="tvt-i tvt-serif r-item" :class="active === {{ $i }} && 'on'" @mouseenter="pick({{ $i }})"><x-filament::icon :icon="$m[2]" />{{ $m[0] }}</div>
                    @endforeach
                </div>
                <div class="tvt-serif" style="position:absolute;right:5%;top:6%;font-size:5.5cqw;z-index:3" x-text="time"></div>
                <div style="position:absolute;left:36%;bottom:9%;z-index:3"><div class="tvt-serif" style="font-size:4.6cqw" x-text="menu[active].label"></div><div style="font-size:1.5cqw;color:#e2d6be" x-text="menu[active].desc"></div></div>
            </div>

            {{-- Tropis --}}
            <div class="tvt-l" x-show="theme === 'tropical'" x-cloak>
                <div class="tvt-photo" style="{{ $bg }}"></div>
                <div class="tvt-l" style="background:linear-gradient(180deg,rgba(6,20,15,.5),transparent 32%,transparent 55%,rgba(6,20,15,.96))"></div>
                <div style="position:absolute;left:4%;top:6%;font-size:1.7cqw;font-weight:800">{{ $hotelName }}</div>
                <div style="position:absolute;right:4%;top:5%;padding:.7cqw 1.8cqw;border-radius:3cqw;background:rgba(6,20,15,.62);border:1px solid rgba(143,214,148,.45);font-size:2.5cqw;font-weight:600" x-text="time"></div>
                <div style="position:absolute;left:4%;bottom:33%"><div style="font-size:1.2cqw;color:#8fd694">Selamat datang</div><div style="font-size:3.2cqw;font-weight:800">Bapak Tamu</div></div>
                <div style="position:absolute;inset:auto 0 5%;display:flex;gap:1cqw;justify-content:center">
                    @foreach ($menu as $i => $m)
                        <div class="tvt-i t-leaf" :class="active === {{ $i }} && 'on'" @mouseenter="pick({{ $i }})"><x-filament::icon :icon="$m[2]" /><div>{{ $m[0] }}<div x-show="active === {{ $i }}" style="font-size:1.1cqw;font-weight:500">{{ $m[1] }}</div></div></div>
                    @endforeach
                </div>
            </div>

            {{-- Senja --}}
            <div class="tvt-l" x-show="theme === 'sunset'" x-cloak>
                <div class="tvt-photo" style="{{ $bg }}"></div>
                <div class="tvt-l" style="background:linear-gradient(180deg,rgba(23,11,10,.72),rgba(23,11,10,.3) 42%,rgba(23,11,10,.85) 70%,#170b0a)"></div>
                <div style="position:absolute;left:4%;top:6%;font-size:1.6cqw;font-weight:800">{{ $hotelName }}</div>
                <div style="position:absolute;inset:9% 0 auto;text-align:center"><div style="font-size:10cqw;font-weight:300;line-height:1" x-text="time"></div><div style="font-size:1.3cqw;color:#f2a65a;margin-top:.6cqw">Selamat datang, Bapak Tamu</div></div>
                <div style="position:absolute;left:0;right:0;bottom:29%;height:0">
                    @foreach ($menu as $i => $m)
                        <div class="tvt-i s-w" :class="Math.abs(d({{ $i }})) < .5 && 'on'" @click="pick({{ $i }})"
                             :style="`transform:translate(${d({{ $i }}) * 15.5}cqw, ${d({{ $i }}) * d({{ $i }}) * 1.2 - 3}cqw) scale(${.62 + .38 * Math.pow(Math.max(0, 1 - Math.abs(d({{ $i }})) / 3), 2)});opacity:${.25 + .75 * Math.max(0, 1 - Math.abs(d({{ $i }})) / 3)}`"><x-filament::icon :icon="$m[2]" /></div>
                    @endforeach
                </div>
                <div style="position:absolute;inset:auto 0 6%;text-align:center"><div style="font-size:2.4cqw;font-weight:700" x-text="menu[active].label"></div><div style="font-size:1.25cqw;color:#ebd6c4" x-text="menu[active].desc"></div></div>
            </div>

            {{-- Nusantara --}}
            <div class="tvt-l" x-show="theme === 'heritage'" x-cloak style="background-color:#171a45">
                <svg class="tvt-l" width="100%" height="100%" aria-hidden="true"><defs><pattern id="tvt-kawung" width="62" height="62" patternUnits="userSpaceOnUse" :patternTransform="`translate(${active * 11} 0)`"><g fill="none" stroke="#e0a93f" stroke-opacity=".24" stroke-width="1.2"><circle cx="15.5" cy="31" r="15.5"/><circle cx="46.5" cy="31" r="15.5"/><circle cx="31" cy="15.5" r="15.5"/><circle cx="31" cy="46.5" r="15.5"/></g></pattern></defs><rect width="100%" height="100%" fill="url(#tvt-kawung)"/></svg>
                <div class="tvt-l" style="background:linear-gradient(90deg,rgba(13,14,43,.9),rgba(13,14,43,.2) 32%,transparent)"></div>
                <div style="position:absolute;left:4%;top:6%;width:24%"><div class="tvt-serif" style="color:#e0a93f;font-size:1.8cqw">{{ $hotelName }}</div><div style="font-size:1cqw;color:#e0a93f;margin-top:1.8cqw">Selamat datang</div><div class="tvt-serif" style="font-size:2.3cqw">Bapak Tamu</div><div style="width:.8cqw;height:.8cqw;background:#e0a93f;transform:rotate(45deg);margin:1.3cqw 0"></div><div class="tvt-serif" style="font-size:5.6cqw;line-height:1" x-text="time"></div></div>
                <div class="h-gate" style="position:absolute;right:4%;top:4%;width:66%;height:62%"><div class="tvt-photo h-gate" style="inset:.6cqw;{{ $bg }}"></div><div class="h-gate" style="position:absolute;inset:.6cqw;background:linear-gradient(180deg,transparent 45%,rgba(13,14,43,.94))"></div><div style="position:absolute;left:3.5cqw;bottom:2.2cqw"><div class="tvt-serif" style="font-size:3.8cqw;line-height:1.1" x-text="menu[active].label"></div><div style="font-size:1.35cqw;color:#dad6e6" x-text="menu[active].desc"></div></div></div>
                <div style="position:absolute;left:4%;right:4%;bottom:3.4%;display:flex;gap:1cqw;align-items:flex-end">
                    @foreach ($menu as $i => $m)
                        <div class="tvt-i h-tile" :class="active === {{ $i }} && 'on'" @mouseenter="pick({{ $i }})"><x-filament::icon :icon="$m[2]" /><span class="tvt-serif">{{ $m[0] }}</span></div>
                    @endforeach
                </div>
            </div>

            {{-- Daerah (Pasundan) --}}
            <div class="tvt-l" x-show="theme === 'sunda'" x-cloak style="background:#2a1a0e">
                <svg class="tvt-l" width="100%" height="100%" aria-hidden="true"><defs><pattern id="tvt-bilik" width="36" height="36" patternUnits="userSpaceOnUse"><g stroke="#e2b04a" stroke-opacity=".13" stroke-width="1.4"><path d="M0 4.5h18M0 9h18M0 13.5h18M18 22.5h18M18 27h18M18 31.500h18M22.500 0v18M27 0v18M31.500 0v18M4.500 18v18M9 18v18M13.500 18v18"/></g></pattern></defs><rect width="100%" height="100%" fill="url(#tvt-bilik)"/></svg>
                <div class="tvt-l" style="background:linear-gradient(90deg,rgba(26,16,9,.86),rgba(26,16,9,.3) 34%,transparent)"></div>
                <div style="position:absolute;left:3.7%;top:5%;width:27%">
                    <div style="font-size:1.3cqw;color:#e2b04a;font-weight:800">{{ $hotelName }}</div>
                    <div style="display:flex;gap:.8cqw;align-items:center;margin-top:1.2cqw">
                        <svg viewBox="0 0 110 220" style="width:4.4cqw;flex:none" aria-hidden="true"><path fill="#e2b04a" d="M46 216h16l-3-64c33-20 43-60 13-90C61 46 63 22 80 2 46 15 28 40 32 68l-18-8 15 32c-2 24 8 44 18 60z"/><g fill="#2a1a0e"><circle cx="52" cy="72" r="5.500"/><circle cx="58" cy="96" r="5.500"/><circle cx="58" cy="120" r="5.500"/></g></svg>
                        <div><div class="tvt-serif" style="font-size:2.8cqw;line-height:1.08;color:#e2b04a">Wilujeng<br>Sumping</div><div style="font-size:1.25cqw;font-weight:700;margin-top:.3cqw">Bapak Tamu</div></div>
                    </div>
                    <div class="tvt-serif" style="font-size:4.6cqw;margin-top:.8cqw;line-height:1.1" x-text="time"></div>
                </div>
                <div style="position:absolute;right:2.3%;top:1.8%;width:67.2%;height:62%">
                    <div style="position:absolute;left:5.600%;right:5.600%;bottom:0;height:80%;border:2px solid #e2b04a;padding:.5cqw"><div style="position:relative;width:100%;height:100%;overflow:hidden"><div class="tvt-photo" style="{{ $bg }}"></div><div class="tvt-l" style="background:linear-gradient(180deg,transparent 42%,rgba(26,16,9,.95))"></div><div style="position:absolute;left:2.200cqw;bottom:1.300cqw"><div class="tvt-serif" style="font-size:3.300cqw;line-height:1.1" x-text="menu[active].label"></div><div style="font-size:1.250cqw;color:#e6d6b8" x-text="menu[active].desc"></div></div></div></div>
                    <svg viewBox="0 0 1290 190" preserveAspectRatio="none" style="position:absolute;left:0;top:0;width:100%;height:27.500%" aria-hidden="true"><path d="M0 190l80-46 88-79h954l88 79 80 46-90-19H90z" fill="#1a1009" stroke="#e2b04a" stroke-width="4"/><g stroke="#e2b04a" stroke-width="9" stroke-linecap="round"><path d="M111 8l100 100M225 8L125 108M1065 8l100 100M1179 8l-100 100"/></g></svg>
                </div>
                <div style="position:absolute;left:2.300%;right:2.300%;bottom:2%;display:flex;gap:1cqw">
                    @foreach ($menu as $i => $m)
                        <div class="tvt-i" style="flex:1" @mouseenter="pick({{ $i }})">
                            <svg viewBox="0 0 100 108" class="s-ang" :class="active === {{ $i }} && 'on'" aria-hidden="true"><g fill="currentColor"><rect x="16" y="2" width="4" height="100"/><rect x="80" y="2" width="4" height="100"/><rect x="12" y="15" width="76" height="3"/><rect x="12" y="62" width="76" height="3"/><rect x="27" y="{{ 34 - $i * 5 }}" width="13" height="{{ 68 + $i * 5 }}" rx="3"/><rect x="44" y="{{ 44 - $i * 4 }}" width="13" height="{{ 58 + $i * 4 }}" rx="3"/><rect x="61" y="{{ 54 - $i * 3 }}" width="13" height="{{ 48 + $i * 3 }}" rx="3"/><rect x="8" y="100" width="84" height="8" rx="3"/></g></svg>
                            <div class="s-base" :class="active === {{ $i }} && 'on'"><x-filament::icon :icon="$m[2]" /><span>{{ $m[0] }}</span></div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="tvt-ctl">
            <button type="button" @click="move(-1)" aria-label="Menu sebelumnya"><x-filament::icon icon="heroicon-m-chevron-left" class="h-4 w-4" /></button>
            <button type="button" @click="move(1)" aria-label="Menu berikutnya"><x-filament::icon icon="heroicon-m-chevron-right" class="h-4 w-4" /></button>
            <span>Coba seperti remote: klik layar lalu tekan tombol panah, atau arahkan kursor ke menu.</span>
        </div>
    </div>
    <div class="tvt-list" role="radiogroup" aria-label="Pilihan tema TV">
        @foreach ($themes as $key => $t)
            <button type="button" role="radio" class="tvt-card" :class="theme === '{{ $key }}' && 'on'" :aria-checked="theme === '{{ $key }}'" @click="theme = '{{ $key }}'">
                <span class="tvt-sw">@foreach ($t['colors'] as $c)<i style="background:{{ $c }}"></i>@endforeach</span>
                <span style="min-width:0;flex:1"><b>{{ $t['name'] }} · {{ $t['style'] }}</b><small>{{ $t['desc'] }}</small></span>
                <x-filament::icon x-show="theme === '{{ $key }}'" x-cloak icon="heroicon-s-check-circle" class="h-5 w-5 text-primary-600" style="flex:none" />
            </button>
        @endforeach
    </div>
</div>
</x-dynamic-component>
