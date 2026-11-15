<x-filament-widgets::widget>
    <div class="vc-card" style="padding:.6rem">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:.1rem .3rem .5rem">
            <div style="display:flex;align-items:center;gap:.5rem;font-weight:700"><x-filament::icon icon="heroicon-o-map" class="vc-ic" />Peta hotel klien</div>
            <div class="vc-muted" style="display:flex;gap:.7rem;font-size:.72rem">
                <span><span class="vc-dot on"></span>Aktif</span><span><span class="vc-dot" style="background:#F59E0B"></span>Segera habis</span><span><span class="vc-dot" style="background:#F43F5E"></span>Ditangguhkan</span>
                @if ($missing)<span>{{ $missing }} hotel belum ada koordinat</span>@endif
            </div>
        </div>
        <div wire:ignore x-data="{
                points: @js($points),
                init() {
                    const go = () => {
                        const map = L.map(this.$refs.map, { scrollWheelZoom: false, attributionControl: true });
                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18, attribution: '© OpenStreetMap' }).addTo(map);
                        const b = [];
                        this.points.forEach(p => {
                            L.circleMarker([p.lat, p.lng], { radius: 9, color: '#fff', weight: 2, fillColor: p.color, fillOpacity: .95 })
                                .bindPopup(`<b>${p.name}</b><br>${p.city ?? ''}<br>${p.tv}<br><a href='${p.url}'>Buka hotel →</a>`).addTo(map);
                            b.push([p.lat, p.lng]);
                        });
                        b.length ? map.fitBounds(b, { padding: [30, 30], maxZoom: 11 }) : map.setView([-2.5, 117.5], 4);
                    };
                    if (window.L) return go();
                    const css = document.createElement('link'); css.rel = 'stylesheet'; css.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'; document.head.appendChild(css);
                    const js = document.createElement('script'); js.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'; js.onload = go; document.head.appendChild(js);
                }
            }">
            <div x-ref="map" style="height:300px;border-radius:12px;overflow:hidden;z-index:0"></div>
        </div>
    </div>
</x-filament-widgets::widget>
