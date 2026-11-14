/*
 * SangaTV Plus CMS service worker.
 * - Static assets (Filament JS/CSS, fonts, icons): stale-while-revalidate.
 * - Pages: always from the network (admin data is never stored on the device);
 *   when offline, a branded offline page is shown instead.
 * - API, Livewire and uploads are never touched.
 */
const VERSION = 'sangatv-cms-v2';
const PRECACHE = ['/offline.html', '/css/sangatv-admin.css', '/css/sangatv-fonts.css',
    '/fonts/PlusJakartaSans-Variable.ttf', '/fonts/Michroma-Regular.ttf', '/icons/icon-192.png'];

self.addEventListener('install', (e) => {
    e.waitUntil(caches.open(VERSION).then((c) => c.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
    e.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== VERSION).map((k) => caches.delete(k))))
            .then(() => self.clients.claim()),
    );
});

const isStatic = (url) =>
    url.origin === self.location.origin &&
    /^\/(css|js|fonts|icons)\//.test(url.pathname);

self.addEventListener('fetch', (e) => {
    const req = e.request;
    if (req.method !== 'GET') return;
    const url = new URL(req.url);
    if (url.origin !== self.location.origin) return;
    if (/^\/(api|livewire|storage|filament\/exports)\//.test(url.pathname)) return;

    if (req.mode === 'navigate') {
        e.respondWith(fetch(req).catch(() => caches.match('/offline.html')));
        return;
    }

    if (isStatic(url)) {
        e.respondWith(
            caches.open(VERSION).then(async (cache) => {
                const hit = await cache.match(req);
                const net = fetch(req).then((res) => {
                    if (res.ok) cache.put(req, res.clone());
                    return res;
                }).catch(() => hit);
                return hit || net;
            }),
        );
    }
});
