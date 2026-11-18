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
.h-tile{position:relative;border:1px solid #6e5a4b;border-radius:.5cqw;padding:1.3cqw;display:flex;flex-direction:column;justify-content:flex-end;gap:.3cqw;font-size:1.75cqw;overflow:hidden;background:#322823;background-size:cover;background-position:center}.h-tile svg{font-size:2.5cqw;color:#c98a3d}
.h-tile.alt{background-color:#2c2e5e}.h-tile.on{transform:scale(1.04);border:.2cqw solid #c98a3d;z-index:2}
.h-tile::after{content:"";position:absolute;right:1.1cqw;top:1.1cqw;width:1cqw;height:1cqw;transform:rotate(45deg);background:#6e5a4b}.h-tile.on::after{background:#c98a3d}
/* Editorial */
.e-m{font-size:1.2cqw;color:#6b6f76;padding-bottom:.6cqw;border-bottom:.26cqw solid transparent;white-space:nowrap}.e-m.on{color:#1a1c1f;font-weight:800;border-color:#ff6b4a}
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
            <div class="tvt-l" x-show="theme === 'heritage'" x-cloak style="background-color:#1c1614">
                <svg class="tvt-l" width="100%" height="100%" aria-hidden="true"><defs><pattern id="tvt-kawung" width="68" height="68" patternUnits="userSpaceOnUse" :patternTransform="`translate(${active * 11} 0)`"><g fill="none" stroke="#c98a3d" stroke-opacity=".17" stroke-width="1.2"><circle cx="17" cy="34" r="17"/><circle cx="51" cy="34" r="17"/><circle cx="34" cy="17" r="17"/><circle cx="34" cy="51" r="17"/></g></pattern></defs><rect width="100%" height="100%" fill="url(#tvt-kawung)"/></svg>
                <div style="position:absolute;left:4.5%;top:17%;width:29%"><div class="tvt-serif" style="color:#c98a3d;font-size:1.9cqw">{{ $hotelName }}</div><div style="width:6cqw;height:2px;background:#c98a3d;margin:1.4cqw 0"></div><div style="font-size:1.1cqw;color:#b5a48f">Selamat datang</div><div class="tvt-serif" style="font-size:2.8cqw">Bapak Tamu</div><div class="tvt-serif" style="font-size:6.2cqw;margin-top:1.5cqw" x-text="time"></div></div>
                <div style="position:absolute;right:4%;top:13%;bottom:13%;width:61%;display:grid;grid-template-columns:repeat(6,1fr);grid-template-rows:4fr 3fr;gap:1.1cqw">
                    @foreach ([0, 1, 5, 2, 3, 4] as $i)
                        <div class="tvt-i h-tile {{ $i % 2 ? '' : 'alt' }}" :class="active === {{ $i }} && 'on'" @mouseenter="pick({{ $i }})" style="grid-column:{{ $i === 0 ? 'span 4' : ($i === 1 || $i === 5 ? 'span 1' : 'span 2') }};{{ $i === 0 ? $bg : '' }}"><x-filament::icon :icon="$menu[$i][2]" /><span class="tvt-serif">{{ $menu[$i][0] }}</span></div>
                    @endforeach
                </div>
            </div>

            {{-- Terang --}}
            <div class="tvt-l" x-show="theme === 'editorial'" x-cloak style="background:#faf8f4;color:#1a1c1f">
                <div class="tvt-i" :style="`position:absolute;top:0;bottom:0;right:0;width:${39.5 + active * 1.8}%`"><div class="tvt-photo" style="{{ $bg }}"></div><div class="tvt-l" style="background:linear-gradient(180deg,transparent 60%,rgba(0,0,0,.7))"></div><div class="tvt-serif" style="position:absolute;right:7%;bottom:7%;font-size:5.8cqw;color:#fff" x-text="time"></div></div>
                <div style="position:absolute;left:4.5%;top:7%;font-size:1.4cqw;font-weight:800">{{ $hotelName }}</div>
                <div style="position:absolute;left:4.5%;top:29%;width:52%"><div style="color:#ff6b4a;font-weight:700;font-size:1.3cqw" x-text="`0${active + 1} / 06`"></div><div class="tvt-serif" style="font-size:7cqw;line-height:1.15;white-space:nowrap" x-text="menu[active].label"></div><div style="font-size:1.55cqw;color:#6b6f76" x-text="menu[active].desc"></div></div>
                <div style="position:absolute;left:4.5%;bottom:9%;display:flex;gap:1.6cqw">
                    @foreach ($menu as $i => $m)
                        <div class="tvt-i e-m" :class="active === {{ $i }} && 'on'" @mouseenter="pick({{ $i }})">{{ $m[0] }}</div>
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
